# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer Bearer {YOUR_ACCESS_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

You can retrieve your access token by logging in via the <code>/api/auth/login</code> endpoint. Include the token in the Authorization header as <code>Bearer {token}</code>. Most endpoints require authentication except for login and registration.
