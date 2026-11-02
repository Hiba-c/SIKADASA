<?php

namespace App\Providers;

use App\Models\ArisanCycle;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\Loan;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Policies\ArisanPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\CashPolicy;
use App\Policies\LoanPolicy;
use App\Policies\MemberPolicy;
use App\Policies\ReportPolicy;
use App\Policies\SavingsPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

//
use Illuminate\Support\Facades\URL;
//

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        // Paksa semua aset dan link menggunakan HTTPS jika diakses lewat proxy/Ngrok
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            URL::forceScheme('https');
        }

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(CashTransaction::class, CashPolicy::class);
        Gate::policy(SavingsAccount::class, SavingsPolicy::class);
        Gate::policy(ArisanCycle::class, ArisanPolicy::class);
        Gate::policy(Loan::class, LoanPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::define('view-reports', fn (User $user): bool => app(ReportPolicy::class)->viewAny($user));
        Gate::define('export-reports', fn (User $user): bool => app(ReportPolicy::class)->export($user));
        Gate::define('validate-reports', fn (User $user): bool => app(ReportPolicy::class)->validate($user));

        RateLimiter::for('login', function (Request $request): Limit {
            $identity = Str::transliterate(Str::lower((string) $request->input('login')));

            return Limit::perMinute(5)->by($identity.'|'.$request->ip());
        });
    }
}
