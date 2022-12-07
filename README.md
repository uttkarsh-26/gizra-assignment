# For backend task:

Adds a new module `server_group` which adds a new plugin to override full view
mode for group node content type.
Plugin class is `NodeGroup`.
All the configs are pushed to default sync folder.
Test `ServerGroupTest` is added to validate the logic.

# For Tailwind task

Adds a new template `server-theme-person-card` and a new method `getPersonCards`
in `StyleGuideController` to invoke this. I have not added a new card grid to be
able to use existing `server-theme-cards` to display a grid of those.
