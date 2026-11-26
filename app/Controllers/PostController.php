<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Participation;
use App\Models\Submission;
use App\Models\ThreadsAccount;

/** Semua utas milik akun, lintas campaign. */
final class PostController
{
    public function index(): void
    {
        $account = ThreadsAccount::forUser((int) Auth::user()['id']);
        $id = (int) ($account['id'] ?? 0);
        $status = in_array(query('status'), ['pending', 'approved', 'rejected', 'kosong'], true) ? query('status') : '';
        $campaign = ctype_digit(query('campaign')) ? (int) query('campaign') : 0;
        $text = mb_substr(query('cari'), 0, 60);
        $page = ctype_digit(query('halaman')) ? max(1, min(1000, (int) query('halaman'))) : 1;
        [$posts, $total] = Submission::forAccount($id, $status, $campaign, $text, $page);
        View::render('posts', [
            'title' => 'Utas', 'active' => 'posts', 'account' => $account, 'posts' => $posts, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / Submission::PAGE),
            'stats' => Submission::accountStats($id), 'campaigns' => $account ? Participation::forAccount($id) : [], 'status' => $status, 'campaign' => $campaign, 'text' => $text,
        ], 'app');
    }
}
