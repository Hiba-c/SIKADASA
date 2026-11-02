<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $exception, Request $request) {
            if (
                $request->expectsJson()
                || $exception instanceof AuthenticationException
                || $exception instanceof ValidationException
            ) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : ($exception instanceof ModelNotFoundException ? 404 : 500);

            if ($status < 400) {
                return null;
            }

            [$title, $message] = match ($status) {
                400 => ['Permintaan tidak valid', 'Periksa kembali data yang dikirim.'],
                401 => ['Perlu masuk', 'Silakan masuk untuk melanjutkan.'],
                403 => ['Akses ditolak', 'Anda tidak memiliki izin untuk membuka halaman atau melakukan tindakan ini.'],
                404 => ['Halaman tidak ditemukan', 'Halaman atau data yang diminta tidak tersedia.'],
                405 => ['Metode tidak didukung', 'Tindakan ini tidak tersedia untuk alamat tersebut.'],
                419 => ['Sesi berakhir', 'Sesi Anda telah berakhir. Muat ulang halaman, lalu coba lagi.'],
                422 => ['Data tidak dapat diproses', 'Periksa kembali data yang dimasukkan.'],
                429 => ['Terlalu banyak permintaan', 'Tunggu sebentar sebelum mencoba kembali.'],
                503 => ['Layanan sementara tidak tersedia', 'Silakan coba kembali beberapa saat lagi.'],
                default => ['Terjadi kesalahan', 'Permintaan belum dapat diproses. Silakan coba kembali.'],
            };

            return Inertia::render('errors/http', [
                'status' => $status,
                'title' => $title,
                'message' => $message,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
