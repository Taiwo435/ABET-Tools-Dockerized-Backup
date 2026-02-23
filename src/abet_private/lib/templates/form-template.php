<link rel="stylesheet" href="/assets/css/form.css">

<div class="center-div">
<div class="form-holder">

<h2 class="form-title"><?= htmlspecialchars($form['title']) ?></h2>

<form action="<?= htmlspecialchars($form['action']) ?>" method="<?= htmlspecialchars($form['method']) ?>" novalidate>

    <?php foreach ($form['fields'] as $field): 
        $name = $field['name'];
        $value = htmlspecialchars($old[$name] ?? '');
        $error = $errors[$name] ?? '';
    ?>

        <div class="form-group" id="<?= $name ?>">
            <label class="form-label"><?= htmlspecialchars($field['label']) ?></label>

            <?php if ($field['type'] === 'select'): ?>

                <select class="form-select" name="<?= $name ?>">
                    <option value="">Select...</option>
                    <?php foreach ($field['options'] as $key => $option): ?>
                        <option value="<?= $key ?>" <?= $value === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($field['type'] === 'expandable-grid'): ?>
            
                <?php $columnCount = count($field['columns']); ?>
                
                <div class="expandable-grid" data-max-rows="<?= $field['maxRows']?>">
                    <div class="expandable-grid-container" 
                        style="grid-template-columns: repeat(<?= $columnCount ?> , 1fr) 0.25fr;">
                        <div class="expandable-grid-row expandable-grid-label-row">
                            <?php foreach ($field['columns'] as $column): ?>
                                <label class="expandable-grid-label">
                                    <?= htmlspecialchars($column) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <!--input rows will be added here-->
                    </div>
                    <button type="button" class="expandable-grid-add-button"
                        onclick="addExpandableGridRow(this.parentElement)">Add Row</button>
                </div>
            <?php else: ?>

                <input
                    class="form-input"
                    type="<?= htmlspecialchars($field['type']) ?>"
                    name="<?= $name ?>"
                    value="<?= $value ?>"
                >

            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error" ><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>

    <?php endforeach; ?>

    <button class="form-button" type="submit">Submit</button>
</form>

<script>
    function addExpandableGridRow (expandableGrid) {
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
        
        for (let i = 0; i < columnCount; i++) {
            const inputField = document.createElement('textarea');
            inputField.className = 'expandable-grid-input';
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
        // condition is "<= 2" because the label row counts as one
        if (expandableGridRow.parentElement.childElementCount <= 2) {
            return;
        }
        
        expandableGridRow.remove();
    }
    
    // Add 1 row to each expandable grid to start
    expandableGrids = document.querySelectorAll('.expandable-grid');
    expandableGrids.forEach((grid) => { addExpandableGridRow(grid); });
</script>

</div>
</div>
