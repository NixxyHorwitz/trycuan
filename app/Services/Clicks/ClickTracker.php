<?php
declare(strict_types=1);

namespace App\Services\Clicks;

use App\Core\ClientIp;
use App\Core\Database;
use App\Core\Env;
use App\Models\Click;
use App\Models\Device;
use App\Models\ShortLink;

final class ClickTracker
{
    public function __construct(
        private readonly Challenge $challenge = new Turnstile(),
        private readonly ClickFilter $filter = new ClickFilter(),
    ) {
    }

    /**
     * Mencatat satu klik. Null bila tidak ada yang dicatat: link tidak aktif, atau
     * token tidak sah, sehingga pengirim asing tidak bisa memenuhi tabel klik.
     *
     * @return 'valid'|'held'|'invalid'|null
     */
    public function record(string $code, ClickRequest $r): ?string
    {
        // Lapis 2: token terikat cookie, jadi tanpa cookie pasti gagal.
        if ($r->cookie === null) return null;
        $campaignId = Token::check($r->token, $code, $r->cookie);
        if ($campaignId === null) return null;
        $link = ShortLink::resolve($code, $campaignId);
        if (!$link || !$link['campaign'] || (int) $link['campaign']['id'] !== $campaignId) return null;
        if (!Token::consume($r->token)) return null;

        $v = new Verdict();
        $this->filter->request($r, $v);
        $this->filter->browser($r, $v);
        if ($v->status() !== 'invalid') $this->filter->challenge($this->challenge->verify($r->challenge, $r->ip), $v);
        $this->filter->network($r, array_filter(explode(',', $link['campaign']['target_countries'])), $v);

        $linkId = (int) $link['id'];
        $deviceId = Device::touch($r->cookie);
        $ip = ClientIp::hash($r->ip);
        $subnet = ClientIp::subnetHash($r->ip);
        $traits = hash_hmac('sha256', 'traits:' . $r->traits(), Env::get('APP_KEY'));
        $this->filter->owner((int) $link['user_id'], $deviceId, $ip, $v);

        $db = Database::pdo();
        $cluster = false;
        $db->beginTransaction();
        try {
            // Klik pada link yang sama diproses bergantian supaya hitungan pola tidak balapan.
            $db->prepare('SELECT id FROM short_links WHERE id = ? FOR UPDATE')->execute([$linkId]);
            $this->filter->pattern($linkId, $campaignId, $deviceId, $ip, $subnet, $traits, $v);
            $cluster = $this->filter->cluster($linkId, $deviceId, $v);

            $st = $db->prepare('SELECT 1 FROM valid_click_keys WHERE short_link_id = ? AND campaign_id = ? AND device_id = ?');
            $st->execute([$linkId, $campaignId, $deviceId]);
            if ($st->fetchColumn()) {
                $v->invalid('perangkat_sama');
            } elseif ($v->status() === 'valid') {
                $db->prepare('INSERT INTO valid_click_keys (short_link_id, campaign_id, device_id) VALUES (?, ?, ?)')->execute([$linkId, $campaignId, $deviceId]);
            }
            $db->prepare('INSERT INTO clicks (short_link_id, campaign_id, device_id, ip_hash, subnet_hash, trait_hash, country, device_type, risk_score, status, reasons_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                $linkId, $campaignId, $deviceId, $ip, $subnet, $traits, $r->country, $r->isMobile() ? 'mobile' : 'desktop',
                min($v->risk, 1000), $v->status(), json_encode($v->reasons, JSON_THROW_ON_ERROR),
            ]);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
        // Di luar transaksi: menyentuh klik milik link lain.
        if ($cluster) {
            $db->prepare("UPDATE clicks SET status = 'held', reasons_json = JSON_ARRAY_APPEND(reasons_json, '$', 'lintas_akun')
                WHERE device_id = ? AND status = 'valid' AND created_at > NOW() - INTERVAL 7 DAY")->execute([$deviceId]);
        }
        if (random_int(1, 200) === 1) Click::purge();
        return $v->status();
    }
}
