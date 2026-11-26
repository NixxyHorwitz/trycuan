<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\View;
use App\Models\Audit;
use App\Models\Campaign;
use App\Models\Report;
use App\Services\CampaignForm;

final class CampaignController
{
    private const FIELDS = ['title', 'brief', 'destination_url', 'required', 'banned', 'min_length', 'rate_per_1k', 'cap_per_account', 'target_countries', 'budget', 'starts_at', 'ends_at', 'status'];

    public function index(): void
    {
        View::render('admin/campaigns', ['title' => 'Kelola campaign', 'active' => 'campaign', 'campaigns' => Campaign::all()], 'admin');
    }

    public function createForm(): void
    {
        $this->form(null, CampaignForm::values(null));
    }

    public function create(): void
    {
        $this->save(null);
    }

    public function editForm(int $id): void
    {
        $campaign = Campaign::find($id) ?? throw new HttpException(404, 'Campaign tidak ditemukan.');
        $this->form($campaign, CampaignForm::values($campaign));
    }

    public function update(int $id): void
    {
        $this->save($id);
    }

    public function summary(int $id): void
    {
        $campaign = Campaign::find($id) ?? throw new HttpException(404, 'Campaign tidak ditemukan.');
        View::render('admin/campaign', ['title' => $campaign['title'], 'active' => 'campaign', 'campaign' => $campaign, 'stats' => Report::campaign($id),
            'chart' => Report::daily(14, $id), 'accounts' => Report::topAccounts($id, 100)], 'admin');
    }

    /** Jeda dan lanjutkan tanpa membuka form. */
    public function status(int $id): void
    {
        $next = ['jeda' => 'paused', 'lanjut' => 'active'][post('tindakan')] ?? throw new HttpException(400, 'Tindakan tidak dikenal.');
        $db = Database::pdo();
        $db->beginTransaction();
        try {
            $campaign = Campaign::find($id, true) ?? throw new HttpException(404, 'Campaign tidak ditemukan.');
            if (Campaign::state($campaign) === 'ended' || $campaign['status'] !== ($next === 'paused' ? 'active' : 'paused')) throw new HttpException(409, 'Status campaign sudah berubah. Muat ulang halaman.');
            $db->prepare('UPDATE campaigns SET status = ? WHERE id = ?')->execute([$next, $id]);
            Audit::add((int) Auth::user()['id'], "campaign.$next", "campaign:$id");
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
        Session::flash($next === 'paused' ? 'Campaign dijeda.' : 'Campaign berjalan lagi.');
        redirect("/admin/campaign/$id/ringkasan");
    }

    /** Form baru berisi salinan; belum tersimpan sampai admin menekan Simpan. */
    public function duplicate(int $id): void
    {
        $campaign = Campaign::find($id) ?? throw new HttpException(404, 'Campaign tidak ditemukan.');
        $length = strtotime($campaign['ends_at']) - strtotime($campaign['starts_at']);
        $start = strtotime('tomorrow');
        $this->form(null, ['title' => mb_substr($campaign['title'], 0, 90) . ' (salinan)', 'status' => 'draft',
            'starts_at' => date('Y-m-d\TH:i', $start), 'ends_at' => date('Y-m-d\TH:i', $start + max(3600, $length))] + CampaignForm::values($campaign));
    }

    private function save(?int $id): void
    {
        $input = [];
        foreach (self::FIELDS as $field) $input[$field] = post($field);

        $db = Database::pdo();
        $db->beginTransaction();
        try {
            // Dikunci supaya tidak ada akun yang ikut di tengah perubahan.
            $current = $id === null ? null : (Campaign::find($id, true) ?? throw new HttpException(404, 'Campaign tidak ditemukan.'));
            $hasAccounts = $id !== null && Campaign::hasAccounts($id);
            [$data, $errors] = CampaignForm::parse($input, $current, $hasAccounts);
            if ($current && Campaign::state($current) === 'ended') $errors = ['form' => 'Campaign yang sudah selesai tidak bisa diubah.'];
            // Campaign ditutup lebih awal: masa pembaruan views dihitung dari sekarang.
            if (!$errors && $data['status'] === 'ended' && strtotime($data['ends_at']) > time()) $data['ends_at'] = date('Y-m-d H:i:s');
            if (!$errors) {
                if ($id === null) $id = Campaign::create($data);
                else Campaign::update($id, $data);
                $db->prepare('INSERT INTO audit_logs (actor_id, action, target, meta_json) VALUES (?, ?, ?, ?)')->execute([
                    Auth::user()['id'], $current ? 'campaign.updated' : 'campaign.created', "campaign:$id",
                    json_encode(['before' => $current, 'after' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]);
            }
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
        if ($errors) {
            $this->form($current, $input, $errors);
            return;
        }
        Session::flash('Campaign disimpan.');
        redirect("/admin/campaign/$id/ringkasan");
    }

    private function form(?array $campaign, array $old, array $errors = []): void
    {
        View::render('admin/campaign_form', [
            'title' => $campaign ? 'Ubah campaign' : 'Campaign baru',
            'active' => 'campaign',
            'campaign' => $campaign,
            'closed' => $campaign && Campaign::state($campaign) === 'ended',
            'hasAccounts' => $campaign && Campaign::hasAccounts((int) $campaign['id']),
            'old' => $old,
            'errors' => $errors,
        ], 'admin', $errors ? 422 : 200);
    }
}
