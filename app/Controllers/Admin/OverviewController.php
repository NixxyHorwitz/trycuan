<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Models\User;

final class OverviewController
{
    public function index(): void
    {
        View::render('admin/overview', ['title' => 'Admin', 'active' => 'admin', 'counts' => User::counts()], 'app');
    }
}
