<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\NftMint;
use Illuminate\Http\Request;

class CostReportsController extends BaseAdminController
{
    public function summary(Request $request)
    {
        $from = $request->query('from', now()->subDays(30)->toDateString());
        $to = $request->query('to', now()->toDateString());

        $base = $this->scopeCompany(
            $request,
            NftMint::query()->whereBetween('created_at', [$from, $to])->whereIn('status', ['sent'])
        );

        $totals = (clone $base)
            ->selectRaw('COUNT(*) as total_mints')
            ->selectRaw('COALESCE(SUM(gas_used), 0) as total_gas_used')
            ->selectRaw('COALESCE(SUM(gas_fee_eth), 0) as total_fee_eth')
            ->selectRaw('COALESCE(SUM(gas_fee_fiat), 0) as total_fee_fiat')
            ->first();

        return $this->ok([
            'period' => compact('from', 'to'),
            'total_mints' => (int) ($totals->total_mints ?? 0),
            'total_gas_used' => (int) ($totals->total_gas_used ?? 0),
            'total_fee_eth' => (float) ($totals->total_fee_eth ?? 0),
            'total_fee_fiat' => (float) ($totals->total_fee_fiat ?? 0),
            'cost_source' => 'nft_mints.gas_fee_*',
        ], 'Cost summary generated');
    }

    public function byCompany(Request $request)
    {
        $query = NftMint::query()->join('payrolls', 'payrolls.id', '=', 'nft_mints.payroll_id');
        if (! $this->isSuperAdmin($request)) {
            $query->where('payrolls.company_id', $this->companyId($request));
        }

        $rows = $query
            ->whereIn('nft_mints.status', ['sent'])
            ->select('payrolls.company_id')
            ->selectRaw('COUNT(*) as total_mints')
            ->selectRaw('COALESCE(SUM(nft_mints.gas_fee_eth), 0) as total_fee_eth')
            ->selectRaw('COALESCE(SUM(nft_mints.gas_fee_fiat), 0) as total_fee_fiat')
            ->groupBy('payrolls.company_id')
            ->get()
            ->map(fn ($r) => [
                'company_id' => $r->company_id,
                'total_mints' => (int) $r->total_mints,
                'total_fee_eth' => (float) $r->total_fee_eth,
                'total_fee_fiat' => (float) $r->total_fee_fiat,
                'cost_source' => 'nft_mints.gas_fee_*',
            ]);

        return $this->ok($rows, 'Cost report by company');
    }

    public function byNetwork(Request $request)
    {
        $query = NftMint::query();
        if (! $this->isSuperAdmin($request)) {
            $query->whereHas('payroll', fn ($q) => $q->where('company_id', $this->companyId($request)));
        }

        $rows = $query
            ->whereIn('status', ['sent'])
            ->selectRaw("COALESCE(network, 'unknown') as network")
            ->selectRaw('COUNT(*) as total_mints')
            ->selectRaw('COALESCE(SUM(gas_fee_eth), 0) as total_fee_eth')
            ->selectRaw('COALESCE(SUM(gas_fee_fiat), 0) as total_fee_fiat')
            ->selectRaw("SUM(CASE WHEN cost_source LIKE 'estimated%' THEN 1 ELSE 0 END) as estimated_count")
            ->groupBy('network')
            ->get()
            ->map(fn ($r) => [
                'network' => $r->network,
                'total_mints' => (int) $r->total_mints,
                'total_fee_eth' => (float) $r->total_fee_eth,
                'total_fee_fiat' => (float) $r->total_fee_fiat,
                'estimated_records' => (int) $r->estimated_count,
            ]);

        return $this->ok($rows, 'Cost report by network');
    }
}
