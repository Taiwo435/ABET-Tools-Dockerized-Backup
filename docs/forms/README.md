# Form System

This project contains a form system which utilizes different files spread throughout the code base.

The form system generates input elements based on forms defined in JSON files to allow for easy creating, editing, and interfacing of forms.

- /abet_private/forms/*
  - The directory where the information that defines a form is stored. See 'defining-forms.md' for more information on defining forms.
- /abet_private/lib/form-database/*
  - Contains any private code that is used to save/load data from the database.
- /abet_private/lib/templates/form-template.php
  - Generates the html and javascript to display the defined form elements for a single form page. 
- /abet_private/lib/templates/form-review-template.php
  - Generates a review page that displays saved information for all of the pages of a form.
- /abet_private/lib/templates/form-page-select-template.php
  - Generates a page where users can choose to go to edit a form page or go to the form review page. Also displays information about the completion status of the form.
- /abet_private/lib/form_functions.php
  - Contains a variety of functions used to implement forms.

See the other documentation files in this folder for more information on the form system.
