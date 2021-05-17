# Details Facets
This module provides alternate modes of viewing the blocks of search facets 
that are provided by the [Facets](https://drupal.org/project/facets) module.
Instead of displaying the facets in a traditional Drupal block they are
displayed within one of Drupal Core's dialog options:
* A modal dialog
* A non-modal dialog
* An off-canvas dialog

This module provides its own blocks, one for each facet, that display a link to
open the dialog.  The link text and dialog type are configured in the block
settings.

This module takes no responsibility for the appearance of the facets within the
off-canvas dialog.  In testing the results were not attractive and would have
required custom theming to fix the appearance.  This dialog option is provided
for anyone who is interested in having it, but be forewarned that you will
likely need to to do some front-end development work to make them look the way
you want.

