# Ionic Angular integration

The following Angular service uses native `HttpClient` and RxJS. Provide HttpClient using your project's existing module or `provideHttpClient()`. This example is an Ionic web application hosted in the same WordPress origin, authenticated with WordPress login cookies and the REST nonce supplied by trusted WordPress PHP. It uses no shared password. Plain-permalink sites should use a configured pretty REST base or adapt URL construction to `rest_route`; the examples assume pretty permalinks.

```typescript
import { Injectable } from '@angular/core';
import { HttpClient, HttpErrorResponse, HttpHeaders } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';

declare global {
  interface Window { quixdevsTickera: { base: string; nonce: string }; }
}

type Status = 'draft' | 'pending' | 'publish' | 'private';
interface ManagedResource {
  id: number; title: string; description: string; status: Status;
  active: boolean; author_id: number; timezone: string;
  created_at: string; updated_at: string;
}
export interface EventRecord extends ManagedResource {
  start_at: string | null; end_at: string | null; location: string; terms: string;
  category_ids: number[]; featured_image_id: number; logo_id: number;
}
export interface TicketRecord extends ManagedResource {
  event_id: number; price: string; quantity: number | null;
  sale_start_at: string | null; sale_end_at: string | null; template_id: number;
}
export interface EventInput {
  title: string; start_at: string; end_at: string; description?: string;
  status?: Status; location?: string; terms?: string; category_ids?: number[];
  featured_image_id?: number; logo_id?: number;
}
export interface TicketInput {
  title: string; price: string; description?: string; status?: Status;
  quantity?: number | null; sale_start_at?: string | null;
  sale_end_at?: string | null; template_id?: number;
}

@Injectable({ providedIn: 'root' })
export class TickeraApi {
  constructor(private http: HttpClient) {}
  private request<T>(method: string, path: string, body?: unknown): Promise<T> {
    const config = window.quixdevsTickera;
    return firstValueFrom(this.http.request<T>(
      method, config.base.replace(/\/$/, '') + path, {
        body, withCredentials: true,
        headers: new HttpHeaders({
          Accept: 'application/json', 'Content-Type': 'application/json',
          'X-WP-Nonce': config.nonce,
        }),
      },
    ));
  }
  events(page = 1) { return this.request<EventRecord[]>('GET', `/events?page=${page}&per_page=20`); }
  event(id: number) { return this.request<EventRecord>('GET', `/events/${id}`); }
  createEvent(input: EventInput) { return this.request<EventRecord>('POST', '/events', input); }
  editEvent(id: number, input: Partial<EventInput>) { return this.request<EventRecord>('PATCH', `/events/${id}`, input); }
  tickets(eventId: number) { return this.request<TicketRecord[]>('GET', `/events/${eventId}/tickets`); }
  ticket(id: number) { return this.request<TicketRecord>('GET', `/tickets/${id}`); }
  createTicket(eventId: number, input: TicketInput) { return this.request<TicketRecord>('POST', `/events/${eventId}/tickets`, input); }
  editTicket(id: number, input: Partial<TicketInput>) { return this.request<TicketRecord>('PATCH', `/tickets/${id}`, input); }

  async workflow() {
    try {
      const mine = await this.events();
      let event = await this.createEvent({ title: 'QuixDevs Conference',
        start_at: '2027-01-14T09:00', end_at: '2027-01-14T18:00', location: 'Dhaka' });
      event = await this.event(event.id);
      event = await this.editEvent(event.id, { location: 'Dhaka Convention Centre' });
      let ticket = await this.createTicket(event.id, { title: 'VIP', price: '50.00', quantity: 100 });
      const tickets = await this.tickets(event.id);
      ticket = await this.ticket(ticket.id);
      ticket = await this.editTicket(ticket.id, { price: '60.00' });
      return { mine, event, tickets, ticket };
    } catch (error) {
      if (error instanceof HttpErrorResponse) {
        if ([400, 422].includes(error.status)) {
          // Bind safe message/data.params to your Ionic form controls.
          console.info('Validation failed', error.error?.data?.params || error.error?.message);
        } else if ([401, 403].includes(error.status)) {
          console.info('Sign in again, refresh nonce, or request author permissions.');
        } else if (error.status === 404) {
          console.info('Resource unavailable to this author.');
        } else if (error.status === 409) {
          console.info('Standalone ticket management is unsupported in this configuration.');
        } else {
          console.info('Inspect resources before retrying a failed create request.');
        }
      }
      throw error;
    }
  }
}
```

Call `await api.workflow()` from an authenticated page; bind returned records to Ionic components. Test validation handling with `api.editTicket(ownedTicketId, {price: '-1'})`. Use `X-WP-TotalPages` from a request with `observe: 'response'` when building a pagination UI.

A **native installed Ionic app** does not automatically share the WordPress browser session. Adapt only the private request helper to send `Authorization: Basic ...` using the current user's separately provisioned Application Password, entered by that user and held through OS Keychain/Keystore integration. Remove cookie/nonce assumptions in that mode. Never embed credentials in this service, environment.ts, mobile assets, source control or localStorage. See [authentication](../authentication.md) for provisioning and lost-device revocation. The addon does not supply a native login screen or token issuer.

Keep price inputs as strings. Interpret datetime-local input in the site's reported timezone. Omit response-only fields and immutable event_id when editing tickets. A user must own both the ticket and its event; client-side checks improve the form but server permissions remain authoritative.
