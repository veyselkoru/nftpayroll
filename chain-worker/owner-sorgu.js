require('dotenv').config({ path: __dirname + '/../.env' });
const { ethers } = require("ethers");

const ABI = [
  "function owner() view returns (address)"
];

async function main() {
  const provider = new ethers.JsonRpcProvider(process.env.CHAIN_RPC_URL);

  const contract = new ethers.Contract(
    process.env.PAYROLL_NFT_CONTRACT_ADDRESS,
    ABI,
    provider
  );

  const owner = await contract.owner();
  console.log(owner);
}

main();
