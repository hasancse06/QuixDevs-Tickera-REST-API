# cURL workflow

For a trusted workstation/backend. Requires cURL and jq. Read [authentication](../authentication.md) and grant the selected user creation/editing capabilities. Supply a per-user password through a secret manager or interactive shell; placeholders below are not real credentials. Avoid putting real values in shell history or running shell tracing. On shared machines use a protected `.netrc`/credential helper rather than exposing credentials in process arguments.

```sh
export API_BASE='https://example.com/wp-json/quixdevs-tickera/v1'
export API_USER='selected_author'
# Provision APP_PASSWORD securely in the environment; never commit it.

# 1. List your events (headers contain totals).
curl --fail-with-body --user "$API_USER:$APP_PASSWORD" -i "$API_BASE/events?page=1&per_page=20"

# 2. Create a draft event and retain its ID.
EVENT_JSON=$(curl --fail-with-body --user "$API_USER:$APP_PASSWORD" \
  -H 'Content-Type: application/json' -X POST "$API_BASE/events" \
  --data '{"title":"QuixDevs Conference","start_at":"2027-01-14T09:00","end_at":"2027-01-14T18:00","location":"Dhaka"}') || exit 1
EVENT_ID=$(printf '%s' "$EVENT_JSON" | jq -r '.id')

# 3. View then partially edit the owned event.
curl --fail-with-body --user "$API_USER:$APP_PASSWORD" "$API_BASE/events/$EVENT_ID"
curl --fail-with-body --user "$API_USER:$APP_PASSWORD" \
  -H 'Content-Type: application/json' -X PATCH "$API_BASE/events/$EVENT_ID" \
  --data '{"location":"Dhaka Convention Centre"}'

# 4. Create a ticket type under that event.
TICKET_JSON=$(curl --fail-with-body --user "$API_USER:$APP_PASSWORD" \
  -H 'Content-Type: application/json' -X POST "$API_BASE/events/$EVENT_ID/tickets" \
  --data '{"title":"VIP","price":"50.00","quantity":100}') || exit 1
TICKET_ID=$(printf '%s' "$TICKET_JSON" | jq -r '.id')

# 5. List/view tickets and edit the owned ticket.
curl --fail-with-body --user "$API_USER:$APP_PASSWORD" "$API_BASE/events/$EVENT_ID/tickets"
curl --fail-with-body --user "$API_USER:$APP_PASSWORD" "$API_BASE/tickets/$TICKET_ID"
curl --fail-with-body --user "$API_USER:$APP_PASSWORD" \
  -H 'Content-Type: application/json' -X PATCH "$API_BASE/tickets/$TICKET_ID" \
  --data '{"price":"60.00","quantity":120}'

# 6. Intentional validation error: observe HTTP 400 and its JSON message.
curl --user "$API_USER:$APP_PASSWORD" -i \
  -H 'Content-Type: application/json' -X PATCH "$API_BASE/tickets/$TICKET_ID" \
  --data '{"price":"-1"}'

# 7. Permission handling: repeat GET with an account lacking edit_tc_events
# to observe 403. Anonymous requests return 401; another author's ID returns
# a generic 404. Do not use response.author_id to authorize on the client.
```

`--fail-with-body` makes HTTP errors fail while retaining the JSON body. Handle 400/422 by correcting fields, 401 by reauthenticating, 403 by requesting administrator-provisioned permissions, 404 as unavailable, and 409 as write contention/unavailable locks or unsupported ticket mode; inspect the error code. On 500 inspect/list before retrying POST. Dates in this example assume the WordPress site timezone, reported as `timezone` in each object.
