<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NftMint extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'wallet_address',
        'ipfs_cid',
        'tx_hash',
        'token_id',
        'status',
        'error_message'
    ];

    protected $appends = [
        'ipfs_url',
        'explorer_url',
    ];

    public function getIpfsUrlAttribute()
    {
        if (! $this->ipfs_cid) {
            return null;
        }

        return 'https://ipfs.io/ipfs/' . $this->ipfs_cid;
    }

    public function getExplorerUrlAttribute()
    {
        if (! $this->tx_hash) {
            return null;
        }

        return 'https://sepolia.etherscan.io/tx/' . $this->tx_hash;
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
