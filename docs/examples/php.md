# PHP backend workflow

Trusted server only, with PHP cURL. Set `TICKERA_API_BASE`, `TICKERA_API_USER`, `TICKERA_API_PASSWORD` in a secret-managed environment. Use a permitted author's Application Password over HTTPS. Never expose this server code's credentials to clients.

```php
<?php
$base = rtrim(getenv('TICKERA_API_BASE'), '/');
if (parse_url($base, PHP_URL_SCHEME) !== 'https') {
    throw new RuntimeException('HTTPS is required.');
}

function tickera_request(string $method, string $path, ?array $body = null): array {
    global $base;
    $handle = curl_init($base . $path);
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => getenv('TICKERA_API_USER') . ':' . getenv('TICKERA_API_PASSWORD'),
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
    }
    curl_setopt_array($handle, $options);
    $raw = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $failure = curl_error($handle);
    curl_close($handle);
    if ($raw === false) {
        throw new RuntimeException('Transport failed: ' . $failure);
    }
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if ($status >= 400) {
        // Only log the safe code/status; never log Authorization headers.
        throw new RuntimeException(($data['code'] ?? 'api_error') . ': ' . ($data['message'] ?? 'Request failed'), $status);
    }
    return $data;
}

try {
    $events = tickera_request('GET', '/events?page=1&per_page=20');
    $event = tickera_request('POST', '/events', [
        'title' => 'QuixDevs Conference',
        'start_at' => '2027-01-14T09:00',
        'end_at' => '2027-01-14T18:00',
        'location' => 'Dhaka',
    ]);
    $id = $event['id'];
    $event = tickera_request('GET', "/events/$id");
    $event = tickera_request('PATCH', "/events/$id", ['location' => 'Dhaka Convention Centre']);
    $ticket = tickera_request('POST', "/events/$id/tickets", ['title' => 'VIP', 'price' => '50.00', 'quantity' => 100]);
    $ticketId = $ticket['id'];
    $tickets = tickera_request('GET', "/events/$id/tickets");
    $ticket = tickera_request('GET', "/tickets/$ticketId");
    $ticket = tickera_request('PATCH', "/tickets/$ticketId", ['price' => '60.00']);
    // Intentional error for a form-validation demonstration:
    tickera_request('PATCH', "/tickets/$ticketId", ['price' => '-1']);
} catch (RuntimeException $error) {
    $status = $error->getCode();
    if ($status === 400 || $status === 422) {
        echo 'Correct the submitted fields: ' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
    } elseif ($status === 401 || $status === 403) {
        echo 'Sign in again or ask an administrator to provision the required permissions.';
    } elseif ($status === 404) {
        echo 'Resource unavailable for this author.';
    } elseif ($status === 409) {
        echo 'Conflict or unsupported ticket mode. Inspect the safe error code and retrieve current state before retrying.';
    } else {
        // POST may already have persisted on a late failure; inspect before retrying.
        echo 'Request failed. Inspect existing resources before retrying.';
    }
}
```

Use decimal strings for prices and integer IDs/quantities. For a WordPress-hosted PHP client, `wp_remote_request()` with the same JSON/authentication is another option; the HTTP identity must still be the intended author. The addon does not let an administrator substitute `author_id` in JSON.
