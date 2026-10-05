import { defineConfig } from '@hey-api/openapi-ts';

export default defineConfig({
  input: '../contracts/openapi/openapi.yaml',
  output: 'src/api/generated',
  parser: {
    patch: {
      input: (spec) => {
        delete spec.servers;
      },
    },
  },
  plugins: [
    '@hey-api/typescript',
    {
      name: '@hey-api/client-axios',
      baseUrl: false,
      bundle: true,
    },
    {
      name: '@hey-api/sdk',
      client: '@hey-api/client-axios',
    },
    'zod',
  ],
});
