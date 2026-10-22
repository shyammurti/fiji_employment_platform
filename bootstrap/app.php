<?php

use App\Http\Middleware\EnsureEmployerApproved;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\App\Http\Middleware\RequireProductionHttps::class);
        // `npm run live` serves the site through a local proxy and Cloudflare Tunnel.
        // Trust forwarded scheme/host/client IP only from processes on this machine.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        $middleware->alias([
            'employer.approved' => EnsureEmployerApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A link to a record that no longer exists (deleted listing, chat,
        // application, user...) explains that it was removed instead of a bare 404.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $missing = $e->getPrevious();
            if (! $missing instanceof ModelNotFoundException) {
                return null;
            }
            $item = match (class_basename($missing->getModel())) {
                'JobListing' => 'job listing',
                'JobApplication' => 'application',
                'MentorshipSession' => 'mentorship session',
                'ChatConversation' => 'chat',
                'User' => 'account',
                default => 'item',
            };
            $message = "This {$item} is no longer available. It may have been removed from the system.";

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'removed' => true], 404)
                : response()->view('errors.removed', ['item' => $item, 'message' => $message, 'errors' => new \Illuminate\Support\ViewErrorBag], 404);
        });
    })->create();
