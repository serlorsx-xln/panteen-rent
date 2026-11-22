<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;

abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $view, array $data = [], string $layout = 'layouts/shop'): void
    {
        View::render($view, $data, $layout);
    }

    /** @param array<string, mixed> $data */
    protected function admin(string $view, array $data = []): void
    {
        View::render('admin/' . $view, $data, 'layouts/admin');
    }

    /**
     * Load a booking and make sure the current user may see it (owner or staff).
     * @return array<string, mixed>
     */
    protected function ownedBooking(int $id): array
    {
        $b = \App\Services\BookingService::find($id);
        if (!$b || (!Auth::isStaff() && (int) $b['user_id'] !== Auth::id())) {
            abort(404); // 404 rather than 403: never reveal that someone else's booking exists
        }
        return $b;
    }

    protected function intId(string $id): int
    {
        if (!ctype_digit($id)) {
            abort(404);
        }
        return (int) $id;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array{total: int, pages: int, page: int, limit: int, offset: int}
     */
    protected function paginate(string $countSql, array $params, int $perPage = 25): array
    {
        $total = (int) DB::value($countSql, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
        return ['total' => $total, 'pages' => $pages, 'page' => $page, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage];
    }
}
