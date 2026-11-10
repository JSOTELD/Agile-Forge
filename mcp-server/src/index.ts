#!/usr/bin/env node
/** AgileForge MCP — servidor completo (lectura + escritura). Bundle: plugin/agileforge. */
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { sessionBriefMain } from './brief.js';
import { WriteClient } from './client-write.js';
import { envWantsView, loadConfig } from './config.js';
import { registerEngineTools } from './tools/engine.js';
import { registerReadResources, registerReadTools } from './tools/read.js';
import { registerWriteTools } from './tools/write.js';

const VERSION = '2.4.0';
// AGILEFORGE_MODE=vista degrada este servidor a solo lectura (usa el token de vista).
const cfg = loadConfig(envWantsView() ? 'vista' : 'control');

if (process.argv.includes('--session-brief')) {
  await sessionBriefMain(cfg);
}

const client = new WriteClient(cfg);
const server = new McpServer({ name: 'agileforge', version: VERSION });

registerReadTools(server, client);
registerReadResources(server, client, { roles: cfg.mode === 'control' });
if (cfg.mode === 'control') {
  registerWriteTools(server, client);
  if (cfg.engineTools) registerEngineTools(server, client);
}

await server.connect(new StdioServerTransport());
