# JavaScript / React / Vue workflow

For a WordPress-hosted, authenticated browser application. Have trusted PHP enqueue your bundle and provide `window.quixdevsTickera = { base: rest_url('quixdevs-tickera/v1'), nonce: wp_create_nonce('wp_rest') }` through WordPress's script APIs. Both values are session-specific public configuration; the nonce is not a password. Use the user's existing same-origin WordPress session. Do not put an Application Password in the bundle.

```javascript
const { base, nonce } = window.quixdevsTickera;

async function api(method, path, body) {
  const response = await fetch(base.replace(/\/$/, '') + path, {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-WP-Nonce': nonce,
      ...(body === undefined ? {} : { 'Content-Type': 'application/json' }) },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });
  const data = await response.json();
  if (!response.ok) {
    throw Object.assign(new Error(data.message || 'API request failed'), {
      status: response.status, code: data.code, fields: data.data?.params,
    });
  }
  return data;
}

async function workflow() {
  try {
    const mine = await api('GET', '/events?page=1&per_page=20');
    let event = await api('POST', '/events', {
      title: 'QuixDevs Conference', start_at: '2027-01-14T09:00',
      end_at: '2027-01-14T18:00', location: 'Dhaka',
    });
    const eventId = event.id;
    event = await api('GET', `/events/${eventId}`);
    event = await api('PATCH', `/events/${eventId}`, { location: 'Dhaka Convention Centre' });
    let ticket = await api('POST', `/events/${eventId}/tickets`, {
      title: 'VIP', price: '50.00', quantity: 100,
    });
    const ticketId = ticket.id;
    const tickets = await api('GET', `/events/${eventId}/tickets`);
    ticket = await api('GET', `/tickets/${ticketId}`);
    ticket = await api('PATCH', `/tickets/${ticketId}`, { price: '60.00' });
    return { mine, event, tickets, ticket };
  } catch (error) {
    if ([400, 422].includes(error.status)) {
      // Attach error.fields to your form and render message as text, never HTML.
      console.info('Correct the submitted fields', error.fields || error.message);
    } else if ([401, 403].includes(error.status)) {
      console.info('Reauthenticate, refresh the REST nonce, or request permissions.');
    } else if (error.status === 404) {
      console.info('This resource is unavailable for the current author.');
    } else if (error.status === 409) {
      console.info('Write conflict or unsupported ticket mode; inspect error.code and retrieve current state before retrying.');
    } else {
      console.info('Request failed; inspect resources before retrying creation.');
    }
    throw error;
  }
}

// Validation example after a successful workflow:
// await api('PATCH', `/tickets/${ticketId}`, { price: '-1' }); // HTTP 400
```

For React/Vue, keep fetched objects in component state; send an explicit writable-field subset on PATCH instead of the whole response. Render `title`/`location` using normal escaped bindings. A browser-native `datetime-local` field supplies the required minute format, but it must represent **the WordPress site timezone**, not automatically the device timezone. Consult the response `timezone` before showing/editing event dates.
