# Defining a Form

The data that defines the input fields on a form is in the '/abet_private/forms' folder.

To see examples on the information below, look at the existing forms defined in the '/abet_private/forms' folder.

Each form has its own subfolder in that folder.

Inside that subfolder is an 'index.json' file. This file contains an array called 'pages'. Each element is a JSON object that represents a page. The 'fileName' attribute corresponds to the name of the file (exluding the .json extension) that defines that page. 
There can also be a 'tableName' attribute that corresponds to the name of a table in the database. This is used by the 'allPagesDone()' function of form_functions as a way to check if an entire form is complete.

The page JSON files should be in the same folder as the 'index.json' file.


## Defining Pages

The page JSON files have different attributes:
- 'title': The title of the page that will be displayed to the user.
- 'name': Should match the name of the file (exluding the .json extension).
- 'action': The php file that will run when the html form is submitted.
- 'method': The request type of the form. Likely "POST".
- 'fields': A list of JSON objects that define all of the input fields and other elements of the form.

## Defining Input Fields

Each field object has its own set of attributes:
- 'type' (string): What type of element the field is (e.g. text field, dropdown select).
- 'name' (string): The key that the data will correspond to when the form submits. Optional if the field is not an input field.
- 'label' (string): The large text label that will appear at the top of the field. Optional.
- 'description' (string): The smaller text label that will appear under the label. Optional.
- 'options' (JSON object): The list of options for select elements. Required for select elements.
- 'columns' (array JSON objects): The list of columns that will appear in the expandable grids. Required for expandable grid elements.
- 'required' (boolan): Determines if the field needs to be filled out before the page is saved. Optional, defaults to false.
- 'minLength' (int): The minimum valid length of the input. Optional.
- 'maxLength' (int): The maximum valid length of the input. Optional.
- 'numerical' (boolean): If true, the input value must be a positive, whole number. Can also be applied to individual columns of expandable grids. Optional, defaults to false.
- 'maxRows' (int): The maximum number of rows that can be on an expandable-grid. Optional, default value determined in form-template.
- 'allowIncompleteRows' (boolean): If true, expandable grid rows can be submitted as partially filled out rows. Otherwise, rows must either have all columns filled out, or be completly empty. Optional, defaults to false.

### Input Field Types

#### section-label

Displays the defined label and description. The label will be larger than normal for 'section-label' elements.

#### section-label-small

Displays the defined label and description. The label will be the normal size.

#### section-break

Creates a horizontal line to visually seperate sections of the form.

#### text

A single-lined text input field.

#### text-field

A multi-lined text input field.

#### select

A dropdown option menu to select 1 option.

Select fields require the 'options' attribute to be defined. The attribute is set to a JSON object. The key in each key-value pair corresponds to what the returned value will be if that option is selected. The value of the key-value pair is the option text that will be displayed to the user.

#### expandable-grid

Expandable grids have multiple defined columns of different input elements. The input types of columns can be different.

Users can add or remove rows to the grid, allthough the row count cannot be less than 1 and cannot be greater than the value defined by 'maxRows'.

When the form is submitted, the data is transported as an array of JSON objects, where each JSON object corresponds to a different row filled out by the user.

Expandable-grids can also use the input type 'checkbox' that returns a boolean.

The 'columns attribute is required to be defined. It is an array of JSON objects, where each object corresponds to a column. The columns are defined with the following attributes.

- 'type' (string): What type of element the field is (e.g. text field, dropdown select).
- 'name' (string): The key that the data will correspond to when the form submits.
- 'options' (JSON object): The list of options for select elements. Required for select elements.
- 'numerical' (boolean): If true, the input value must be a positive, whole number. Can also be applied to individual columns of expandable grids. Optional, defaults to false.