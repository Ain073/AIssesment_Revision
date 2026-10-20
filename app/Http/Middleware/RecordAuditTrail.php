<?php

namespace App\Http\Middleware;

use App\Services\AuditTrailRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordAuditTrail
{
    public function handle(Request $request, Closure $next): Response
    {
        // Authentication attempts keep durable rate-limit counters. Successful
        // login has its own required audit write in LoginController.
        if ($request->routeIs('login.submit') || ($request->isMethodSafe()
            && ! $request->routeIs('student.assessments.start', 'student.assessments.take', 'instructor.reports.build'))) {
            return $next($request);
        }

        $recorder = app(AuditTrailRecorder::class);
        $connection = DB::connection();
        $level = $connection->transactionLevel();
        $connection->beginTransaction();
        try {
            $recorder->start($request);
            $response = $next($request);
            if ($response->getStatusCode() >= 400) {
                $connection->rollBack($level);

                return $response;
            }
            $recorder->flush();
            $connection->commit();

            return $response;
        } catch (Throwable $exception) {
            if ($connection->transactionLevel() > $level) {
                $connection->rollBack($level);
            }
            throw $exception;
        } finally {
            $recorder->reset();
        }
    }
}
