# API examples

All examples use fake data and placeholders. Use the intended author's own HTTPS Application Password or WordPress cookie/nonce context. Do not paste production credentials into code, shared shell history or browser bundles.

- [curl: all eight operations](examples/curl.md)
- [JavaScript: WordPress cookie/nonce workflow](examples/javascript.md)
- [PHP: trusted backend workflow](examples/php.md)
- [Ionic Angular integration boundary](examples/ionic-angular.md)
- [Importable Postman collection](postman/quixdevs-tickera-rest-api.postman_collection.json)
- [Full request fields, synthetic responses and error statuses](rest-api.md)

POST returns 201; reads and PATCH return 200. Use the returned ID in later calls. Send JSON price strings, integer quantities and site-local dates. A 409 can mean a contended/unavailable write lock or unsupported Bridge/relationship mode; inspect its safe error code. A late 500 can follow partial persistence: inspect the current record/list before retrying POST.

The [README quick examples](../README.md#api-request-examples) cover listing owned events, creating an event, listing an owned event's ticket types and PATCHing an owned ticket type. The language guides above provide all eight operations, error handling and safe authentication setup. No purchasing or payment examples are supported.
