This is a demo project featuring an Address book.

Technical content:

- a basic MVC model
- a basic router
- a basic DB manager class, based on PDO
- Twig as a template engine
- Bootstrap CSS from CDN
- jQuery from CDN
- Composer (also for autoloding self made namespaces)

Features:

- Contact page:
  + Create, modify, delete contacts
  + Add a contact to a Group
  + Add multiple comma separated tags to a contact
  + Filter contacts by tags
  + Export contact list in XML and JSON format
- Group page:
  + Create, modify, delete groups
  + Inherit contacts from a child group and from all its descendants
  + Eliminate duplicate contacts, keeping only the inherited ones
  + List contacts of a group (inherited and own)
