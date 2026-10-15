# Ownership and capabilities

Authentication establishes the WordPress user. Every event requires the actual post_author to equal that user. Every ticket requires both ticket ownership and an owned, editable tc_events parent linked through Tickera's event_name metadata. The registered CPT edit meta capability is checked with the actual post ID. Administrators have no ownership exception.

New resources always receive the authenticated creator as post_author; no reassignment field exists. Ticket creation requires the route parent to be owned and editable. An optional POST event_id must match that parent. PATCH rejects event_id, author_id, post_author, meta, meta_input and every unknown key. Direct service calls apply the same field schema and recheck authorization before persistence.

Foreign/nonexistent IDs, wrong post types, orphaned/foreign parents and excluded statuses return generic 404. Owned resources lacking edit permission return 403. Anonymous requests return 401. Collection queries constrain author, parent association and editable statuses on the server; client author/meta query overrides are rejected. Publication/private creation requires the registered publish capability.

| Two-author scenario | Expected |
|---|---|
| A creates event/ticket beneath A's event | 201, authorship A |
| A lists/views/patches A's editable objects | 200, A's data only |
| B lists own objects after A creates objects | A absent from rows/totals |
| B GET/PATCH A's event or ticket | 404, unchanged storage |
| B POST ticket under A's event | 404, no ticket |
| A owns ticket but B owns parent | 404 |
| A owns parent but B owns ticket | ticket omitted; detail/PATCH 404 |
| A forges owner/meta or moves ticket | 400 |
| Administrator requests another author's object | 404 |
| Owner lacks publication/edit capability | 403 |

These scenarios are exercised with actual WordPress users and Tickera records in tests/Security and tests/Integration. No policy claim is made for independent native Tickera or wp/v2 routes; this policy governs this addon's namespace only. Revocation should include the user's Application Passwords when appropriate.
