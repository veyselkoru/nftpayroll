<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

trait AuthorizesCompany
{
    /**
     * Şirket owner kontrolü
     */
    protected function authorizeCompany(User $user, Company $company): void
    {
        if ((int) $company->owner_id !== (int) $user->id) {
            throw new AuthorizationException('You are not allowed to access this company.');
        }
    }

    /**
     * Şirket + çalışan eşleşmesi
     */
    protected function authorizeEmployee(User $user, Company $company, Employee $employee): void
    {
        $this->authorizeCompany($user, $company);

        if ((int) $employee->company_id !== (int) $company->id) {
            throw new AuthorizationException('Employee does not belong to this company.');
        }
    }

    /**
     * Şirket + çalışan + payroll eşleşmesi
     */
    protected function authorizePayroll(User $user, Company $company, Employee $employee, Payroll $payroll): void
    {
        $this->authorizeEmployee($user, $company, $employee);

        if ((int) $payroll->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('Payroll does not belong to this employee.');
        }
    }
}
