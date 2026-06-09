<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses hanya untuk akun ber-peran admin.
 *
 * Dipasang setelah middleware `auth`, sehingga user pasti sudah login di sini;
 * non-admin ditolak dengan 403 (bukan pengguna yang berhak).
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Akses ditolak. Halaman ini khusus administrator.');
        }

        return $next($request);
    }
}
