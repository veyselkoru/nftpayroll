// chain-worker/mint.js — Laravel MintPayrollNftJob için final sürüm

const path = require("path");
require("dotenv").config({
  path: path.resolve(__dirname, "..", ".env"), // 👈 Laravel .env
});

const { ethers } = require("ethers");

const ABI = [
  "function mintTo(address to, string uri) external returns (uint256)"
];

const RPC_URL = process.env.CHAIN_RPC_URL;
const PRIVATE_KEY = process.env.CHAIN_PRIVATE_KEY;
const CONTRACT_ADDRESS = process.env.PAYROLL_NFT_CONTRACT_ADDRESS;

async function main() {
  const [,, to, uri] = process.argv;

  // Argüman kontrolü
  if (!to || !uri) {
    process.stderr.write("MISSING_ARGUMENTS");
    process.exit(1);
  }

  // ENV kontrolü
  if (!RPC_URL || !PRIVATE_KEY || !CONTRACT_ADDRESS) {
    process.stderr.write("MISSING_ENV");
    process.exit(1);
  }

  const provider = new ethers.JsonRpcProvider(RPC_URL);
  const wallet   = new ethers.Wallet(PRIVATE_KEY, provider);
  const contract = new ethers.Contract(CONTRACT_ADDRESS, ABI, wallet);

  try {
    const tx = await contract.mintTo(to, uri);
    await tx.wait();

    // ❗ Laravel sadece bunu okuyacak
    process.stdout.write(tx.hash);

  } catch (err) {
    process.stderr.write(err.message || String(err));
    process.exit(1);
  }
}

main();
