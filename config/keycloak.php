<?php 

return [
  'realm_public_key' => env('KEYCLOAK_REALM_PUBLIC_KEY', null),

  'load_user_from_database' => env('KEYCLOAK_LOAD_USER_FROM_DATABASE', true),

  'user_provider_credential' => env('KEYCLOAK_USER_PROVIDER_CREDENTIAL', 'username'),

  'token_principal_attribute' => env('KEYCLOAK_TOKEN_PRINCIPAL_ATTRIBUTE', 'preferred_username'),

  'append_decoded_token' => env('KEYCLOAK_APPEND_DECODED_TOKEN', false),

  'allowed_resources' => env('KEYCLOAK_ALLOWED_RESOURCES', null),

  'user_url' => env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users',

  'execute_email' => '/execute-actions-email?client_id=' . env('KEYCLOAK_BACKEND_CLIENT') . '&redirect_uri=' . env('REACT_APP_BASE_URL'),

  'groups_url' => env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/groups',
];
