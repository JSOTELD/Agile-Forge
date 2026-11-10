#!/usr/bin/env node
/**
 * AgileForge Vista — servidor MCP de SOLO LECTURA. Bundle: plugin/agileforge-vista.
 * No importa nada de escritura (client-write, tools/write, tools/engine).
 */
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { sessionBriefMain } from './brief.js';
import { ReadClient } from './client.js';
import { loadConfig } from './config.js';
import { registerReadResources, registerReadTools } from './tools/read.js';
const cfg = loadConfig('vista');
if (process.argv.includes('--session-brief')) {
    await sessionBriefMain(cfg);
}
const client = new ReadClient(cfg);
const server = new McpServer({ name: 'agileforge-vista', version: '2.1.0' });
registerReadTools(server, client);
registerReadResources(server, client, { roles: false });
await server.connect(new StdioServerTransport());
