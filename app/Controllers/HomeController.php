<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class HomeController
{
    public function index(): void
    {
        View::render('home', ['title' => 'TryCuan: dibayar dari views Threads']);
    }

    public function privacy(): void
    {
        View::render('privacy', ['title' => 'Privasi']);
    }
}
