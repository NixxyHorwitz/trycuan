<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\View;
use App\Models\Audit;
use App\Models\Ledger;
use App\Models\Participation;
use App\Models\ShortLink;
use App\Models\ThreadsAccount;
use App\Models\User;
use App\Services\Csv;
use App\Services\Threads\Identifier;
use App\Services\Threads\ThreadsException;
use App\Services\Threads\ThreadsScraper;
use App\Services\UserAdmin;
use App\Services\VerificationException;

final class UserController
{
    public function index(): void
    {
        [$query, $status, $sort] = self::filter();
        $page = max(1, min(100000, (int) query('halaman')));
        [$users, $total] = User::search($query, $status, $sort, $page);
        View::render('admin/users', ['title' => 'Freelancer', 'active' => 'users', 'users' => $users, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / User::PAGE)),
            'query' => $query, 'status' => $status, 'sort' => $sort, 'counts' => User::counts()], 'admin');
    }

    public function export(): void
    {
        [$query, $status, $sort] = self::filter();
        [$users] = User::search($query, $status, $sort, 1, 10000);
        Audit::add((int) Auth::user()['id'], 'user.exported', 'users', ['rows' => count($users)]);
        Csv::send('freelancer', ['ID', 'Nama', 'Email', 'Status', 'Daftar', 'Masuk terakhir', 'Threads', 'Saldo', 'Views'], $users);
    }

    private static function filter(): array
    {
        return [mb_substr(query('cari'), 0, 80), in_array(query('status'), ['active', 'blocked'], true) ? query('status') : '', in_array(query('urut'), ['saldo', 'views', 'lama'], true) ? query('urut') : ''];
    }

    public function detail(int $id): void
    {
        $this->show($id);
    }

    public function action(int $id): void
    {
        $admin = (int) Auth::user()['id'];
        $reason = trim((string) preg_replace('/[\s\p{C}]+/u', ' ', post('alasan')));
        $do = post('tindakan');
        try {
            if (in_array($do, ['blokir', 'buka', 'lepas'], true) && (mb_strlen($reason) < 3 || mb_strlen($reason) > 300)) {
                throw new VerificationException('Isi alasan, 3 sampai 300 karakter.');
            }
            switch ($do) {
                case 'blokir':
                    UserAdmin::setStatus($id, 'blocked', $admin, $reason);
                    Session::flash('User diblokir.');
                    break;
                case 'buka':
                    UserAdmin::setStatus($id, 'active', $admin, $reason);
                    Session::flash('Blokir dibuka.');
                    break;
                case 'lepas':
                    UserAdmin::detach($id, $admin, $reason);
                    Session::flash('Akun Threads dilepas.');
                    break;
                case 'username':
                    $username = Identifier::username(post('username')) ?? throw new VerificationException('Username tidak valid.');
                    UserAdmin::rename($id, $username, $admin, new ThreadsScraper());
                    Session::flash('Username diperbarui.');
                    break;
                default:
                    throw new HttpException(400, 'Tindakan tidak dikenal.');
            }
        } catch (VerificationException $error) {
            $this->show($id, $error->getMessage());
            return;
        } catch (ThreadsException $error) {
            $this->show($id, $error->outcome->message());
            return;
        }
        redirect("/admin/user/$id");
    }

    private function show(int $id, ?string $error = null): void
    {
        $row = User::find($id) ?? throw new HttpException(404, 'User tidak ditemukan.');
        if ($row['role'] === 'admin' && !$error) throw new HttpException(404, 'Admin dikelola di halaman Tim admin.');
        $account = ThreadsAccount::forUser($id);
        View::render('admin/user', [
            'title' => $row['name'],
            'active' => 'users',
            'row' => $row,
            'account' => $account,
            'link' => $account ? ShortLink::forAccount((int) $account['id']) : null,
            'parts' => $account ? Participation::forAccount((int) $account['id']) : [],
            'entries' => Ledger::forUser($id, 20),
            'balance' => Ledger::balance($id),
            'errors' => $error ? ['form' => $error] : [],
        ], 'admin', $error ? 422 : 200);
    }
}
