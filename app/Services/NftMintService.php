<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Web3\Web3;
use Web3\Contract;
use Web3p\EthereumTx\Transaction;
use App\Services\AbiEncoder;

class NftMintService
{
    protected string $rpcUrl;
    protected string $privateKey;
    protected string $ownerAddress;
    protected string $contractAddress;

    public function __construct()
    {
        $this->rpcUrl          = env('CHAIN_RPC_URL');
        $this->privateKey      = env('CHAIN_PRIVATE_KEY');
        $this->ownerAddress    = env('CHAIN_OWNER_ADDRESS');
        $this->contractAddress = env('PAYROLL_NFT_CONTRACT_ADDRESS');

        Log::info('NftMintService init', [
            'rpc'      => $this->rpcUrl,
            'owner'    => $this->ownerAddress,
            'contract' => $this->contractAddress,
        ]);
    }

    /**
     * @return string tx hash
     */
    public function mintTo(string $toAddress, string $tokenUri): string
    {
        $web3 = new Web3($this->rpcUrl);

        // ✔ Manuel encoded data
        $data = AbiEncoder::encodeMintTo($toAddress, $tokenUri);

        // nonce
        $nonceHex = null;
        $web3->eth->getTransactionCount($this->ownerAddress, 'pending', function($err, $res) use (&$nonceHex) {
            if ($err) throw new \Exception($err->getMessage());
            $nonceHex = $res; 
        });

        if (!$nonceHex) throw new \Exception("Nonce alınamadı");

        $gasLimit = '0x493e0';      // 300000
        $gasPrice = '0x4a817c800';  // 20 gwei

        $tx = [
            'nonce'    => $nonceHex,
            'from'     => $this->ownerAddress,
            'to'       => $this->contractAddress,
            'gas'      => $gasLimit,
            'gasPrice' => $gasPrice,
            'value'    => '0x0',
            'data'     => $data,
            'chainId'  => 11155111,
        ];

        $pk = ltrim($this->privateKey, '0x');
        $rawTx = new Transaction($tx);
        $signed = '0x' . $rawTx->sign($pk);

        $txHash = null;

        $web3->eth->sendRawTransaction($signed, function($err, $res) use (&$txHash) {
            if ($err) throw new \Exception($err->getMessage());
            $txHash = $res;
        });

        if (!$txHash) throw new \Exception("Tx hash alınamadı");

        return $txHash;
    }

    
}
