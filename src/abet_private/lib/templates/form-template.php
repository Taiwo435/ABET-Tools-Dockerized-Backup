<?php // requires $form, $backendErrorMessage, and $old to be set beforehand ?>

<link rel="stylesheet" href="/assets/css/form.css">

<div class="center-div">
<div class="form-holder">

<div class="top-bar">
    <h2 class="form-title"><?= htmlspecialchars($form['title']) ?></h2>
    <button class="form-button form-button-return" onclick="returnToPageSelect()">Return to Page Select (Without Saving)</button>
</div>

<?php if ($backendErrorMessage !== ''): ?>
    <h2 class="form-error">Warning. This page was not saved. The server gave the following message:<br><?= $backendErrorMessage ?></h2>
<?php endif ?>

<form action="<?= htmlspecialchars($form['action']) ?>" 
    method="<?= htmlspecialchars($form['method']) ?>" novalidate
    data-name="<?= $form['name'] ?>"
    data-form-group="<?= htmlspecialchars($formName) ?>">

    <?php foreach ($form['fields'] as $field): 
        $name = $field['name'];
        $type = $field['type'];
        $raw = $old[$name] ?? '';
        $value = is_string($raw) ? htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') : '';
        $error = $errors[$name] ?? '';
    ?>


        <?php if ($type === 'section-break'): ?>
            <div class="form-section-break"></div>
            <?php continue; ?>
        <?php endif ?>


        <div class="form-group" id="<?= $name ?>">
            <?php if (!empty($field['label'])): ?>
                <label 
                    class="form-label 
                        <?php if ($type==="section-label") {echo "form-section-label";}?>
                        <?php if ($type==="section-label-small") {echo "form-section-label-small";}?>"
                >

                    <div><?= htmlspecialchars($field['label']) ?></div>
                    &nbsp;
                    <div class="form-required-star"><?php if ($field['required']) { echo ("      *"); } ?></div>
                </label>
            <?php endif ?>

            

            <?php if (!empty($field["description"])): ?>
                <p class="form-field-description">
                    <?=  htmlspecialchars($field["description"]) ?>
                </p>
            <?php endif; ?>



            <?php if ($type === 'select' || $type === 'select-multiple'): ?>

                <select class="form-select" name="<?= $name ?>" <?php if ($type === 'select-multiple') { echo 'multiple'; } ?>>
                    <option value="">Select...</option>
                    <?php foreach ($field['options'] as $key => $option): ?>
                        <option value="<?= $key ?>" <?= $value === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>



            <?php elseif ($type === 'expandable-grid'): ?>
                <?php
                $gridOldJson = "";
                if (is_array($raw)){
                    $gridOldJson = json_encode($raw);
                } elseif (is_string($raw)){
                    $gridOldJson = $raw;
                }
                ?>
                <?php $columnCount = count($field['columns']); ?>
                
                <div class="expandable-grid" data-max-rows="<?= $field['maxRows']?>">
                    <div class="expandable-grid-container"
                        name="<?= $name ?>"
                        data-old-values='<?= htmlspecialchars(json_encode($raw ?? ""), ENT_QUOTES, "UTF-8") ?>'
                        data-old-values2=<?= json_encode($value) ?>
                        style="grid-template-columns: repeat(<?= $columnCount ?> , 1fr) 0.25fr;">
                        <div class="expandable-grid-row expandable-grid-label-row">
                            <?php foreach ($field['columns'] as $column): ?>
                                <label class="expandable-grid-label" 
                                    data-type="<?= htmlspecialchars($column['type'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-name="<?= htmlspecialchars($column['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?php if (!empty($column['options'])){
                                        echo "data-option-count=" . count($column['options']) . " ";
                                        for ($i = 0; $i < count($column['options']); $i++) {
                                            echo "data-option-" . $i . "='" . $column["options"][$i] . "' ";
                                        }
                                    } ?>
                                    >
                                    <?= htmlspecialchars($column['label']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <!--input rows will be added here-->
                    </div>
                    <button type="button" class="expandable-grid-add-button"
                        onclick="addExpandableGridRow(this.parentElement)">Add Row</button>
                </div>



            <?php elseif ($type === 'text'): ?>
                <input
                    class="form-input"
                    type="<?= htmlspecialchars($type) ?>"
                    name="<?= $name ?>"
                    value="<?= $value ?>"
                >



            <?php elseif ($type === 'text-field'): ?>
                <textarea 
                    class="form-text-field" 
                    name="<?= $name ?>"
                    ><?= $value ?></textarea>
            <?php endif; ?>




            <?php if ($error): ?>
                <div class="error" ><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>

    <?php endforeach; ?>

    <div class="bottom-buttons">
        <button class="form-button form-button-save" type="button" onclick="saveOnly()">Save Page</button>
        <button class="form-button form-button-save-continue" type="button" onclick="saveAndContinue()">Save & Next Page</button>
    </div>
    
    <div class="form-error-holder"></div>
</form>

<script>
// Currently some of this code is built around there being only 1 form on the page_name
// Will need to be changed if multiple forms on the same page

function submitForm (currentPageNumber, nextPageNumber) {
    console.log("Submitting...");

    if (!validateForm()) {
        return;
    }

    prepareDataFromGridElements();

    const form = document.querySelector('form');

    const currentPageNumberInput = document.createElement('input');
    currentPageNumberInput.type = "hidden";
    currentPageNumberInput.name = "current_page_number";
    currentPageNumberInput.value = currentPageNumber;
    form.appendChild(currentPageNumberInput);

    const nextPageNumberInput = document.createElement('input');
    nextPageNumberInput.type = "hidden";
    nextPageNumberInput.name = "next_page_number";
    nextPageNumberInput.value = nextPageNumber;
    form.appendChild(nextPageNumberInput);

    const pageNameInput = document.createElement('input');
    pageNameInput.type = "hidden";
    pageNameInput.name = "page_name";
    pageNameInput.value = "placeholder";
    pageNameInput.value = form.getAttribute('data-name');
    form.appendChild(pageNameInput);
    

    form.submit();
}

function saveOnly () {
    currentPage = <?= $_GET['page']; ?>;
    submitForm(currentPage, currentPage);
}

function saveAndContinue () {
    currentPage = <?= $_GET['page']; ?>;
    submitForm(currentPage, currentPage + 1);
}

function loadDataToGridElements() {
    expandableGrids = document.querySelectorAll('.expandable-grid');
    expandableGrids.forEach((grid) => { 
        const gridContainer = grid.querySelector('.expandable-grid-container');
        oldValues = gridContainer.getAttribute('data-old-values2')
        if (oldValues == "") {
            addExpandableGridRow(grid);
            return;
        }
        
        oldRowData = JSON.parse(oldValues);
        if (oldRowData.length == 0) {
            addExpandableGridRow(grid);
            return;
        }
        oldRowData.forEach((rowData, rowIndex) => {
            addExpandableGridRow(grid, rowData);
        });
    });
}
function prepareDataFromGridElements() {
    expandableGrids = document.querySelectorAll('.expandable-grid-container');
    expandableGrids.forEach((grid, gridIndex) => {
        rows = [];

        inputRows = grid.querySelectorAll('.expandable-grid-input-row');       
        inputRows.forEach((row, rowIndex) => {
            rowJSON = {};

            inputElements = row.children;
            inputElementsArray = Array.from(inputElements);
            let isEmptyRow = true;
            inputElementsArray.forEach((element, elementIndex) => {
                // Ignores the remove buttons
                if (element.classList.contains('expandable-grid-remove-button')) {
                    return;
                }

                if (element.value !== "") {
                    isEmptyRow = false;
                }

                columnName = grid.querySelector('.expandable-grid-label-row').children[elementIndex].getAttribute("data-name");
                rowJSON[columnName] = element.value;
                
            });
            if (!isEmptyRow) {
                rows.push(rowJSON);
            }
        });

        const expandableGridInput = document.createElement('input');
        expandableGridInput.type = "hidden";
        expandableGridInput.name = grid.getAttribute("name");
        expandableGridInput.value = JSON.stringify(rows);

        const form = document.querySelector('form');
        form.appendChild(expandableGridInput);
    });
}

function addExtendableGridRow(expandableGrid) {
    addExtendableGridRow(extendableGrid, null);
}

function addExpandableGridRow (expandableGrid, oldRowDataJSON) {
    const gridContainer = expandableGrid.querySelector('.expandable-grid-container');
    
    let maxRows = expandableGrid.getAttribute('data-max-rows');
    maxRows = parseInt(maxRows);
    if (Number.isNaN(maxRows)) {
        // Default value
        maxRows = 10;
    }
    
    if (gridContainer.childElementCount >= maxRows + 1) {
        return;
    }
    
    const columnCount = gridContainer.querySelector('.expandable-grid-label-row').childElementCount;
    
    const rowDiv = document.createElement('div');
    rowDiv.className = 'expandable-grid-row expandable-grid-input-row';
    
    const labelRow = gridContainer.querySelector('.expandable-grid-label-row');
    for (let i = 0; i < columnCount; i++) {
        const label = labelRow.children[i]
        const columnType = label.getAttribute('data-type');
        const columnName = label.getAttribute('data-name');
        let inputField;
        
        if (columnType === 'select' || columnType === 'select-multiple') {
            inputField = document.createElement('select');
            const optionCount = labelRow.children[i].getAttribute('data-option-count');

            const defaultOption = document.createElement('option');
            defaultOption.value = "";
            defaultOption.textContent = "Select...";
            inputField.appendChild(defaultOption);
            for (let j = 0; j < optionCount; j++) {
                const newOption = document.createElement('option');
                newOption.value = label.getAttribute('data-option-' + j);
                newOption.textContent = label.getAttribute('data-option-' + j);
                inputField.appendChild(newOption);
            }
            if (columnType === 'select-multiple') {
                inputField.multiple = true;
            }
            inputField.className = 'expandable-grid-select';
            if (oldRowDataJSON != null) {
                inputField.value = oldRowDataJSON[columnName];
            }
        } else if (columnType === 'text') {
            inputField = document.createElement('input');
            inputField.className = 'expandable-grid-small-input';
            if (oldRowDataJSON != null) {
                inputField.value = oldRowDataJSON[columnName];
            }
        } else {
            inputField = document.createElement('textarea');
            inputField.className = 'expandable-grid-large-input';
            if (oldRowDataJSON != null) {
                inputField.textContent = oldRowDataJSON[columnName];
            }
        }
        rowDiv.appendChild(inputField);
    }
    
    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'expandable-grid-remove-button';
    removeButton.textContent = "Remove";
    removeButton.onclick = function() { removeExpandableGridRow(this.parentElement) };
    rowDiv.appendChild(removeButton);
    
    gridContainer.appendChild(rowDiv);
}

function removeExpandableGridRow (expandableGridRow) {
    // Ensures a row can't be removed if it is the only one
    if (expandableGridRow.parentElement.querySelectorAll('.expandable-grid-input-row').length <= 1) {
        return;
    }
    
    expandableGridRow.remove();
}

function returnToPageSelect() {
    const form = document.querySelector('form');
    const formName = form.getAttribute('data-form-group');
    window.location.assign('/' + formName);
}


function validateForm() {
    const form = document.querySelector("form");
    const formConfig = <?= json_encode($form, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    let errors = [];

    form.querySelector('.form-error-holder').replaceChildren();

    formConfig.fields.forEach(field => {
        const input = form.querySelector(`[name="${field.name}"]`);
        if (!input) return;
        clearError(input);
        let value = "";
        if (field.type !== "expandable-grid") {
            value = input.value.trim();
        }

        // Make sure expandable-grid is checked first because its logic differs
        if (field.type === "expandable-grid") {
            const expandableGridRows = input.querySelectorAll('.expandable-grid-input-row');

            let hasAFullRow = false;
            expandableGridRows.forEach((row, rowIndex) => {
                rowElements = row.children;
                rowElementsArray = Array.from(rowElements);
                let isEmptyRow = true;
                let isFullRow = true;
                rowElementsArray.forEach((element, elementIndex) => {
                    /*if (element.getAttribute('data-numerical') == 'true' && !isPositiveWholeNumber(value)) {
                        errors.push(`${label.value} must be entered as a positive, whole number.`);
                        showError(input, `${label.value} must be entered as a positive, whole number.`);
                        return;
                    }*/

                    if (element.classList.contains('expandable-grid-remove-button')) {
                        return;
                    }

                    if (element.value.trim() !== '') {
                        isEmptyRow = false;
                    } else if (element.value.trim() === '') {
                        isFullRow = false;
                    }
                });

                if (isEmptyRow) {
                    removeExpandableGridRow(row);
                    return;
                }
                if (!isFullRow) {
                    errors.push("Rows cannot be partially filled out. Either fill out all parts of the row or remove the row.");
                    showError(input, "Rows cannot be partially filled out. Either fill out all parts of the row or remove the row.");
                    return;
                } else {
                    hasAFullRow = true;
                }
            });

            if (field.required && !hasAFullRow) {
                errors.push(`${field.label} is required. Complete at least one row.`);
                showError(input, `${field.label} is required. Complete at least one row.`);
                return;
            }
            // Return here because we the other checks don't work with expandable grids.
            return;
        }

        // Required validation
        if (field.required && value === "") {
            errors.push(`${field.label} is required.`);
            showError(input, `${field.label} is required.`);
            return;
        }
        
        // Numerical field validation
        if (field.numerical && value != "") {
            newValue = value.replace(/%/g, '');
            if (!isPositiveWholeNumber(newValue)) {
                errors.push(`${field.label} must be entered as a positive, whole number.`)
                showError(input, `${field.label} must be entered as a positive, whole number.`)
                return;
            }
        }

        // Min length validation
        if (field.minLength && value.length < field.minLength) {
            errors.push(`${field.label} must be at least ${field.minLength} characters.`);
            showError(input, `${field.label} must be at least ${field.minLength} characters.`);
        }

        // Email validation
        if (field.type === "email" && value !== "") {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                errors.push("Invalid email format.");
                showError(input, "Invalid email format.");
            }
        }
    });
    
    if (errors.length > 0) {
        const formErrorDiv = form.querySelector('.form-error-holder');
        const formError = document.createElement('div');
        formError.classList = "error";
        formError.textContent = "Errors in form detected. Please fix before saving.";
        formErrorDiv.appendChild(formError);

        return false;
    }

    return true;
}

function showError(input, message) {
    clearError(input);

    const error = document.createElement("div");
    error.className = "error";
    error.innerText = message;

    input.parentNode.appendChild(error);
    input.classList.add("input-error");
}

function clearError(input) {
    const existing = input.parentNode.querySelector(".error");
    if (existing) existing.remove();
    input.classList.remove("input-error");
}

function isPositiveWholeNumber(value) {
    const num = Number(value);
    if (!Number.isInteger(num) || num < 0) {
        return false
    }
    return true
}

loadDataToGridElements();
</script>


</div>
</div>
