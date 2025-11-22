require("dotenv").config();
const { ethers } = require("ethers");

const ABI = [
  "function transferOwnership(address newOwner) external",
  "function owner() view returns (address)"
];

const RPC = process.env.CHAIN_RPC_URL;
const CONTRACT = process.env.PAYROLL_NFT_CONTRACT_ADDRESS;
const CHAIN_PRIVATE_KEY = process.env.CHAIN_PRIVATE_KEY;  // Bu DOĞRU KEY

async function main() {
  const provider = new ethers.JsonRpcProvider(RPC);
  const wallet = new ethers.Wallet(CHAIN_PRIVATE_KEY, provider);
  const contract = new ethers.Contract(CONTRACT, ABI, wallet);

  const newOwner = "0x125E82e69A4b499315806b10b9678f3CDE6B977E";

  const current = await contract.owner();
  console.log("Current owner:", current);

  const tx = await contract.transferOwnership(newOwner);
  console.log("Sending tx:", tx.hash);

  await tx.wait();
  console.log("Owner changed to:", newOwner);
}

main();
