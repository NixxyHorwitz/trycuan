<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\Validate;
use App\Core\View;
use App\Models\Audit;
use App\Models\Campaign;
use App\Models\CampaignDoc;
use App\Services\Markdown;
use App\Services\PayoutException;
use App\Services\ProofFile;

/** Menyusun materi dan panduan campaign. */
final class DocController
{
    private const FILE_MAX = 8 * 1024 * 1024;

    public function index(int $id): void
    {
        $this->list($id);
    }

    public function createForm(int $id): void
    {
        $this->campaign($id);
        $this->form($id, null, ['kind' => query('jenis') === 'materi' ? 'materi' : 'panduan', 'title' => '', 'body' => '', 'status' => 'published']);
    }

    public function create(int $id): void
    {
        $this->campaign($id);
        $this->save($id, null);
    }

    public function edit(int $id): void
    {
        $doc = $this->doc($id);
        $this->form((int) $doc['campaign_id'], $doc, $doc);
    }

    public function update(int $id): void
    {
        $doc = $this->doc($id);
        $campaignId = (int) $doc['campaign_id'];
        switch (post('tindakan')) {
            case 'simpan':
                $this->save($campaignId, $doc);
                return;
            case 'naik':
            case 'turun':
                CampaignDoc::move($doc, post('tindakan') === 'naik' ? -1 : 1);
                break;
            case 'hapus':
                CampaignDoc::delete($id);
                Audit::add((int) Auth::user()['id'], 'doc.deleted', "campaign:$campaignId", ['title' => $doc['title']]);
                Session::flash('Dokumen dihapus.');
                break;
            default:
                throw new HttpException(400, 'Tindakan tidak dikenal.');
        }
        redirect("/admin/campaign/$campaignId/dokumen");
    }

    public function upload(int $id): void
    {
        $this->campaign($id);
        $file = is_array($_FILES['berkas'] ?? null) ? $_FILES['berkas'] : [];
        try {
            $stored = ProofFile::store($file, 'berkas', self::FILE_MAX);
        } catch (PayoutException $error) {
            $this->list($id, ['berkas' => $error->getMessage()]);
            return;
        }
        // Nama asli hanya untuk tampilan; ekstensi mengikuti isi.
        $name = pathinfo(Validate::cleanName(is_string($file['name'] ?? null) ? mb_scrub($file['name']) : ''), PATHINFO_FILENAME);
        $name = trim((string) preg_replace('/[^\p{L}\p{N} ._-]+/u', '', mb_substr($name, 0, 80))) ?: 'berkas';
        $size = (int) filesize(ProofFile::path($stored));
        $fileId = CampaignDoc::addFile($id, $name . '.' . pathinfo($stored, PATHINFO_EXTENSION), $stored, $size);
        Audit::add((int) Auth::user()['id'], 'doc.file_added', "campaign:$id", ['file' => $fileId, 'name' => $name]);
        Session::flash('Berkas diunggah.');
        redirect("/admin/campaign/$id/dokumen");
    }

    public function deleteFile(int $id): void
    {
        $file = CampaignDoc::file($id) ?? throw new HttpException(404, 'Berkas tidak ditemukan.');
        CampaignDoc::deleteFile($id);
        ProofFile::delete($file['file']);
        Audit::add((int) Auth::user()['id'], 'doc.file_deleted', "campaign:{$file['campaign_id']}", ['name' => $file['name']]);
        Session::flash('Berkas dihapus.');
        redirect("/admin/campaign/{$file['campaign_id']}/dokumen");
    }

    public function file(int $id): void
    {
        $file = CampaignDoc::file($id);
        if (!$file || !is_file(ProofFile::path($file['file']))) throw new HttpException(404, 'Berkas tidak ditemukan.');
        ProofFile::send($file['file'], $file['name']);
    }

    /** Pratinjau saat menulis. */
    public function preview(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo (new Markdown('x-', '/admin/materi/'))->render(mb_substr(post('body'), 0, 60000));
    }

    private function save(int $campaignId, ?array $doc): void
    {
        $data = ['kind' => post('kind'), 'title' => Validate::cleanName(post('title')), 'body' => trim(str_replace("\r\n", "\n", post('body'))), 'status' => post('status')];
        $errors = array_filter([
            'kind' => isset(CampaignDoc::KINDS[$data['kind']]) ? null : 'Pilih jenis.',
            'title' => mb_strlen($data['title']) >= 3 && mb_strlen($data['title']) <= 120 ? null : 'Judul 3 sampai 120 karakter.',
            'body' => $data['body'] !== '' && mb_strlen($data['body']) <= 60000 ? null : 'Isi dokumen, maksimal 60.000 karakter.',
            'status' => in_array($data['status'], ['draft', 'published'], true) ? null : 'Pilih status.',
        ]);
        if ($errors) {
            $this->form($campaignId, $doc, $data, $errors);
            return;
        }
        $id = CampaignDoc::save($doc ? (int) $doc['id'] : null, $campaignId, $data);
        Audit::add((int) Auth::user()['id'], $doc ? 'doc.updated' : 'doc.created', "campaign:$campaignId", ['doc' => $id, 'title' => $data['title'], 'status' => $data['status']]);
        Session::flash('Dokumen disimpan.');
        redirect("/admin/campaign/$campaignId/dokumen");
    }

    private function campaign(int $id): array
    {
        return Campaign::find($id) ?? throw new HttpException(404, 'Campaign tidak ditemukan.');
    }

    private function doc(int $id): array
    {
        return CampaignDoc::find($id) ?? throw new HttpException(404, 'Dokumen tidak ditemukan.');
    }

    private function list(int $id, array $errors = []): void
    {
        View::render('admin/docs', ['title' => 'Materi dan panduan', 'active' => 'campaign', 'campaign' => $this->campaign($id), 'docs' => CampaignDoc::forCampaign($id, false), 'files' => CampaignDoc::files($id), 'errors' => $errors], 'admin', $errors ? 422 : 200);
    }

    private function form(int $campaignId, ?array $doc, array $old, array $errors = []): void
    {
        View::render('admin/doc_form', ['title' => $doc ? 'Ubah dokumen' : 'Dokumen baru', 'active' => 'campaign', 'campaign' => $this->campaign($campaignId), 'doc' => $doc, 'files' => CampaignDoc::files($campaignId), 'old' => $old, 'errors' => $errors], 'admin', $errors ? 422 : 200);
    }
}
