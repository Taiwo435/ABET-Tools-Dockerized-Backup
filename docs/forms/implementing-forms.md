# Implementing a Form

For examples on the following implementation, see '/public/faculty-form/' or '/public/coordinator-form/'

The following structure is reccomended to implement a form:
- *formName*-form
  - edit
    - index.php
    - submit.php
  - review
    - index.php
  - index.php (page select)

## Implementing the Edit Page

Inside the 'index.php' file corresponding to the form edit:

The '/abet_private/lib/templates/auth-handler.php' file must be imported first if session variables are to be used.

Use a 'page' parameter in the URL when accessing the edit pages in order to select which form page is being edited.

Logic to ensure the page value is valid must be implemented. Functions in the form_functions file can help with this.

Before the template is imported, a few variables need to be set.
- $form
  - The data of the defined elements for the form page. Can be set with loadFormPage() from the form_functions file.
- $old
  - A JSON of any old data. Could be data loaded from the database, or data restored after a failed save attempt. JSON keys must correspond to the 'name' attributes of the form fields.
- $backendErrorMessage
  - A string displaying an error message from the backend if the page was loaded by the backend in response to an error. Is usually set to a blank string to signify no error.

Then, import the form-template file with the php 'require_once' function.

### Submit file

'Submit.php' may be called something else if the action attribute in the page JSON file was something different.

The '/abet_private/lib/templates/auth-handler.php' file must be imported first if session variables are to be used here or in any save files that are imported.

The submit.php file should import or include logic to save the submitted data. The data is contained in the '$_POST' variable.

The file should also handle redirecting to the next page after save functions are completed.


## Implementing the Page Select

Inside the 'index.php' file corresponding to the page select:

Before the template is imported, a few variables need to be set.
- $formName
  - The name of the folder in '/abet_private/forms' that defines the desired form
- $formDisplayTitle
  - The title of the form that will be displayed to users
- $formBasePath
  - The path to the folder that holds the form data, relative to '/abet_private/forms'.
- $formCssPath
  - The path to the folder that holds css for the page, relative to '/public'.
- $completeMessage
  - The message that will be displayed to the user when all pages are complete.
- $incompleteMessage
  - The message that will be displayed to the user when there are incomplete pages.

Additionally, functions to load the saved data need to be imported beforehand.

Then, import the page select template with the php 'require_once' function.


## Implementing the Form Review

Inside the 'index.php' file corresponding to the form review:

Before the template is imported, a few variables need to be set.
- $formName
  - The name of the folder in '/abet_private/forms' that defines the desired form
- $formBasePath
  - The path to the folder that holds the form data, relative to '/abet_private/forms'.
- $reviewTitle
  - The title on the page that will be displayed to the user.
- $reviewCssPath
  - The path to the folder that holds css for the page, relative to '/public'.

Additionally, functions to load the saved data need to be imported beforehand.

Then, import the review template with the php 'require_once' function.