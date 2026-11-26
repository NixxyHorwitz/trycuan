<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\View;
use App\Models\Campaign;
use App\Models\CampaignDoc;
use App\Models\Participation;
use App\Models\ThreadsAccount;
use App\Services\Markdown;
use App\Services\ProofFile;

/** Materi dan panduan campaign untuk freelancer. */
final class DocController
{
    public function show(int $id): void
    {
        $campaign = $this->campaign($id);
        $docs = [];
        foreach (CampaignDoc::forCampaign($id, true) as $doc) {
            $markdown = new Markdown("d{$doc['id']}-");
            $docs[] = $doc + ['html' => $markdown->render($doc['body']), 'toc' => $markdown->toc];
        }
        View::render('campaigns/docs', ['title' => 'Materi dan panduan', 'active' => 'campaign', 'campaign' => $campaign, 'docs' => $docs, 'files' => CampaignDoc::files($id)], 'app');
    }

    public function file(int $id): void
    {
        $file = CampaignDoc::file($id) ?? throw new HttpException(404, 'Berkas tidak ditemukan.');
        $this->campaign((int) $file['campaign_id']);
        if (!is_file(ProofFile::path($file['file']))) throw new HttpException(404, 'Berkas tidak ditemukan.');
        ProofFile::send($file['file'], $file['name']);
    }

    /** Aturan terlihat sama dengan halaman campaign. */
    private function campaign(int $id): array
    {
        $campaign = Campaign::find($id);
        $account = ThreadsAccount::forUser((int) Auth::user()['id']);
        $joined = $account && Participation::find((int) $account['id'], $id);
        $state = $campaign ? Campaign::state($campaign) : 'draft';
        if ($state === 'draft' || (!$joined && (in_array($state, ['ended', 'paused'], true) || !Campaign::offered($id)))) throw new HttpException(404, 'Campaign tidak ditemukan atau sudah ditutup.');
        return $campaign;
    }
}
