# Postman

Import `quixdevs-tickera-rest-api.postman_collection.json` (collection format 2.1). Set `base_url` to your HTTPS API namespace, `username` to the permitted author's WordPress login and `application_password` to that user's locally stored secret. Leave passwords out of exported/shared collections; use a local secret/environment value or Postman Vault according to your workstation setup. The committed password variable is empty.

Run **List your events → Create an event → Retrieve your event → Partially edit your event → List tickets for your event → Create a ticket type → Retrieve your ticket type → Partially edit your ticket type**. Create requests capture returned IDs into `event_id`/`ticket_id` automatically. The initial list of tickets can be empty; run it again after creating the ticket. Default IDs 123/456 and stored response examples are synthetic placeholders, not fixtures on your site.

The collection inherits Basic authentication on all eight operations. Event/ticket bodies contain only implemented fields. Inspect status and JSON errors; do not blindly retry POST on transport/500 failures. Refer to [rest-api.md](../rest-api.md) for permissions, sale dates, template/image fields and Bridge limitations.
