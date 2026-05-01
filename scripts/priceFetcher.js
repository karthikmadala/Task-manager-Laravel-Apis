// priceFetcher.js
// Simple Node utility to fetch real‑time USD prices for one or more crypto tokens
// using the CoinGecko "simple price" endpoint. Intended for use by transaction
// handling code that needs the latest market value.

const https = require('https');

/**
 * Fetch USD price for given CoinGecko token IDs.
 * @param {string[]} ids - Array of CoinGecko token IDs (e.g. ['bitcoin','ethereum']).
 * @returns {Promise<Object>} Resolves with an object mapping token ID to { usd: number }.
 */
function fetchPrices(ids) {
  if (!Array.isArray(ids) || ids.length === 0) {
    return Promise.reject(new Error('ids must be a non‑empty array'));
  }
  const query = ids.map(encodeURIComponent).join(',');
  const url = `https://api.coingecko.com/api/v3/simple/price?ids=${query}&vs_currencies=usd`;
  return new Promise((resolve, reject) => {
    https.get(url, (res) => {
      let data = '';
      res.on('data', (chunk) => (data += chunk));
      res.on('end', () => {
        try {
          const parsed = JSON.parse(data);
          resolve(parsed);
        } catch (e) {
          reject(e);
        }
      });
    }).on('error', reject);
  });
}

// Command‑line usage: node priceFetcher.js bitcoin ethereum
if (require.main === module) {
  const args = process.argv.slice(2);
  if (args.length === 0) {
    console.error('Usage: node priceFetcher.js <coin-id> [<coin-id> ...]');
    process.exit(1);
  }
  fetchPrices(args)
    .then((prices) => {
      console.log(JSON.stringify(prices, null, 2));
    })
    .catch((err) => {
      console.error('Error fetching prices:', err.message);
      process.exit(1);
    });
}

module.exports = { fetchPrices };
