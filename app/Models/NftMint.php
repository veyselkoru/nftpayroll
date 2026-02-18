<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NftMint extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'company_id',
        'wallet_address',
        'ipfs_cid',
        'tx_hash',
        'token_id',
        'network',
        'gas_used',
        'gas_fee_eth',
        'gas_fee_fiat',
        'cost_source',
        'duration_ms',
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

    public function getImageUrlAttribute()
    {
        if (!$this->ipfs_cid) return null;

        // Görsel kendi imageCID üzerinden
        $imageCid = env('NFTPAYROLL_IMAGE_CID');
        return "https://ipfs.io/ipfs/{$imageCid}";
    }
}
