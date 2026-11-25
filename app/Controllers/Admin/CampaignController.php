<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\View;
use App\Models\Campaign;
use App\Services\CampaignForm;

final class CampaignController
{
    private const FIELDS = ['title', 'brief', 'destination_url', 'required', 'banned', 'min_length', 'rate_per_1k', 'cap_per_account', 'target_countries', 'budget', 'starts_at', 'ends_at', 'status'];

    public function index(): void
    {
        View::render('admin/campaigns', ['title' => 'Kelola campaign', 'active' => 'admin', 'campaigns' => Campaign::all()], 'app');
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
        redirect('/admin/campaign');
    }

    private function form(?array $campaign, array $old, array $errors = []): void
    {
        View::render('admin/campaign_form', [
            'title' => $campaign ? 'Ubah campaign' : 'Campaign baru',
            'active' => 'admin',
            'campaign' => $campaign,
            'closed' => $campaign && Campaign::state($campaign) === 'ended',
            'hasAccounts' => $campaign && Campaign::hasAccounts((int) $campaign['id']),
            'old' => $old,
            'errors' => $errors,
        ], 'app', $errors ? 422 : 200);
    }
}
