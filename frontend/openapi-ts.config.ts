import { defineConfig } from '@hey-api/openapi-ts';

export default defineConfig({
  input: '../contracts/openapi/05A_OpenAPI_3.1.yaml',
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
