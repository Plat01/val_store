const { Client } = require('@modelcontextprotocol/sdk/client/index.js');
const { StdioClientTransport } = require('@modelcontextprotocol/sdk/client/stdio.js');
(async () => {
  const client = new Client({ name: 'soberi-stanok-check', version: '1.0.0' });
  const transport = new StdioClientTransport({ command: process.execPath,
    args: [require('node:path').join(require('node:path').dirname(require.resolve('@playwright/mcp/package.json')), 'cli.js'), '--headless', '--browser', 'chromium'] });
  try {
    await client.connect(transport);
    const result = await client.callTool({ name: 'browser_navigate', arguments: { url: process.env.WP_URL || 'http://localhost:8080/catalog/' } });
    if (result.isError) throw new Error(JSON.stringify(result));
    console.log(JSON.stringify(result.content).slice(0,1800));
    await client.callTool({ name: 'browser_close', arguments: {} });
    console.log('Playwright MCP: OK');
  } finally { await client.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
