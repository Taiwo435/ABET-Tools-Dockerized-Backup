<?php
require_once getenv('ABET_PRIVATE_DIR') . '/lib/csrf.php';
require_login();
$csrfToken = csrf_token('tool1_proxy');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Connect to className | ABET Tools</title>
  <link rel="stylesheet" href="/assets/css/tool1.css">
  <link rel="stylesheet" href="/assets/css/select-courses.css">
</head>
<body>

  <header class="site-header">
    <div class="site-title">ABET Tools - Connect to Class</div>
    <div class="header-actions">
      <a href="jobs.php" class="btn-header">View Jobs</a>
      <a href="/../index.php" class="nav-link">← Back to Dashboard</a>
    </div>
  </header>


    <div class="main-container">

    <div class="page-header" style = "display: flex; justify-content: space-between; align-items: center;">
        <div class="page-title">
            <h1>Add Canvas Classes</h1>
            <p>Add your class courses</p>
        </div>
    </div>

    <div class="filter-bar">
        <div class="filter-group">
            <label for="semesterFilter" class ="filter-label"> Semester </label>
            <select class="filter-select" id="semesterFilter"></select>
        </div>  
        <div class="filter-group">
            <label for="departmentFilter" class ="filter-label"> Department </label>
            <select class="filter-select" id="departmentFilter">
                <option value="Biological and Health Systems Engineering"> School of Biological and Health Systems Engineering </option>
                <option value="(SCAI)"> School of Computing and Augmented Intelligence (SCAI) </option>
                <option value="(SECEE)"> School of Electrical, Computer and Energy Engineering (SECEE) </option>
                <option value="(SEMTE)"> School for Engineering of Matter, Transport and Energy (SEMTE) </option>
                <option value="(SSEBE)"> School of Sustainable Engineering and the Built Environment (SSEBE) </option>
                <option value="(MSN)"> School of Manufacturing Systems and Networks (MSN) </option>
                <option value="Polytechnic School"> The Polytechnic School </option>
                <option value="Integrated Engineering"> School of Integrated Engineering </option>
            </select>
        </div>  

        </div>

        <div id="selection-bar" style="display: none; justify-content: space-between; align-items: center; margin: 15px 0; padding: 15px 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid var(--border-color);">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700; color: var(--asu-maroon); user-select: none;">
                <input type="checkbox" id="selectAll" style="width: 18px; height: 18px; cursor: pointer;"> 
                Select All
            </label>
            <button class="btn btn-primary" id="next-btn">Next Page →</button>
        </div>
        <label style="display: none; color: var(--warning); padding: 10px; font-weight: bold; text-align: center;" id="warning_next_page">Cannot go to next page without selected classes</label>
        <div class="classes-grid" id="classesGrid">
        </div>


        <div class="alert alert-success" id="successAlert">
          <div class="alert-content">
            <strong>Successfully connected!</strong>
            <div style="margin-top: 15px;">
              <a href="select-courses.php" class="btn btn-primary">Continue to Select Courses →</a>
            </div>
          </div>
        </div>

        <div class="alert alert-error" id="errorAlert">
          <div class="alert-content">
            <strong>Connection failed</strong>
            <span id="errorMessage"></span>
          </div>
        </div>
      </div>

    <script>
        let csrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>'
        const courses = {}

        document.getElementById('next-btn').addEventListener('click', async function()
          {
            const classesGrid = document.getElementById('classesGrid')
            const warning_text = document.getElementById('warning_next_page')

            //If there are no classes shown, give warning text & do not show button
            if (!classesGrid.hasChildNodes())
            {
                warning_text.style.visibility = "visible"
                return
            }
           
            let selected_courses = []
            
            //Add Checked Classes to list
            for (const child of classesGrid.children)
            {
              const checked = child.querySelector('.class-header .card-checkbox').checked
              if (checked)
              {
                const classCode = child.querySelector('.class-header .class-code').textContent.trim()
                selected_courses.push(courses[classCode])
              }
            }
            console.log(selected_courses)

            if (selected_courses.length === 0) {
                showError('Please select at least one course.');
                return;
            }

            try {
              const storeBody = new FormData()
              storeBody.append('action', 'store-class-data-from-grid')
              storeBody.append('selected_courses_data', JSON.stringify(selected_courses))
              storeBody.append('csrf_token', csrfToken)
              const storeRes = await fetch('api-proxy.php', { method: 'POST', body: storeBody })
              const storeData = await storeRes.json()
              if (storeData.next_csrf) csrfToken = storeData.next_csrf;
              if (!storeData.success) {
                //showError(storeData.message || 'Failed to store credentials.');
                return;
              }
            } catch (err) {
              //showError('Network error: ' + err.message);
            } finally {
              //connectBtn.disabled = false;
              //connectBtn.textContent = '🔗 Verify & Connect';
            }
            window.location.href = 'course-setup.php'
          })
        
        function showSuccess(course) {
          errorAlert.style.display = 'none';
          duplicateAlert.style.display = 'none';
          document.getElementById('connectedCourse').textContent =
            course.name + ' (' + course.course_code + ')';
          document.getElementById('connectedTerm').textContent =
            course.term || 'N/A';
          successAlert.style.display = 'block';
          successAlert.scrollIntoView({ behavior: 'smooth' });
        }

        function showError(msg) {
            successAlert.style.display = 'none'
            errorAlert.style.display = 'block'
            document.getElementById('errorMessage').textContent =
                typeof msg === 'string' ? msg : JSON.stringify(msg)
            errorAlert.scrollIntoView({ behavior: 'smooth' })
        }

        function createClassHeader(course)
        {
            class_header_div = document.createElement('div')
            class_header_div.className = "class-header"
            class_header_div.id = "class-header-id"

            const checkbox = document.createElement('input')
            checkbox.type = "checkbox"
            checkbox.className = "card-checkbox"
            checkbox.checked = false
            
            const class_code_div = document.createElement('div')
            class_code_div.className = "class-code"
            class_code_div.textContent = course['course_code']
            class_code_div.id = "class-code-id"

            const class_title_h3 = document.createElement('h3')
            class_title_h3.className = "class-header"
            class_title_h3.textContent = course['name']

            const class_meta_div = document.createElement('div')
            class_meta_div.className = "class-meta";

            const semester_badge_span = document.createElement('span')
            semester_badge_span.className = "semester-badge"
            semester_badge_span.textContent = course['term']['name']
            
            const student_span_account = document.createElement('span')

            class_header_div.appendChild(checkbox)
            class_header_div.appendChild(class_code_div)
            class_header_div.appendChild(class_title_h3)
            class_meta_div.appendChild(semester_badge_span)
            class_meta_div.appendChild(student_span_account)
            class_header_div.append(class_meta_div)
            return class_header_div
        }

        function createClassBody()
        {
            const class_body_div = document.createElement('div')
            class_body_div.className = "class-body"

            const class_info_grid_div = document.createElement('div')
            class_info_grid_div.className = "class-info-grid"
            class_info_grid_div.style.gridTemplateColumns = "1fr";

            const info_item_div = document.createElement('div')
            const info_label_div = document.createElement('div')
            const info_value_div = document.createElement('div')
            info_item_div.className = "info-item"
            info_label_div.className = "info-label"
            info_label_div.textContent = "Instructor"
            info_value_div.className = "info-value"
            info_value_div.textContent = "TBD" // To be populated from whitelist API later
            
            info_item_div.append(info_label_div, info_value_div)
            class_info_grid_div.appendChild(info_item_div)
            class_body_div.appendChild(class_info_grid_div)
            return class_body_div
        }

        function createClassActions(course)
        {
            const class_actions_div = document.createElement('div')
            class_actions_div.className = "class-actions"

            const view_assignments = document.createElement('a')
            view_assignments.href = "index.php"
            view_assignments.className = "action-btn"
            view_assignments.textContent = "View Assignments"

            const gradebook = document.createElement('a')
            gradebook.href = "#"
            gradebook.className = "action-btn"
            gradebook.textContent = "Gradebook"

            const analytics = document.createElement('a')
            analytics.href = "#"
            analytics.className = "action-btn"
            analytics.textContent = "Analytics"
            
            class_actions_div.append(view_assignments, gradebook, analytics)
            return class_actions_div
        }

        document.getElementById('selectAll').addEventListener('change', function(e) {
            const isChecked = e.target.checked;
            document.querySelectorAll('.card-checkbox').forEach(cb => {
                cb.checked = isChecked;
                const card = cb.closest('.class-card');
                if (card) {
                    if (isChecked) card.classList.add('selected-card');
                    else card.classList.remove('selected-card');
                }
            });
            const nextBtn = document.getElementById('next-btn');
            if (nextBtn) nextBtn.disabled = (!isChecked || document.querySelectorAll('.card-checkbox').length === 0);
        });
        
        function addInitialSemesters()
        {
            const currentDate = new Date().toISOString().slice(0,10);
            const year = currentDate.substr(0,4);
            const month = Number(currentDate.substr(5,2));
            const semesterFilter = document.getElementById('semesterFilter');
            if (month <= 5)
            {   
                const spring_semester = document.createElement('option')
                spring_semester.value = "Sp" + year;
                spring_semester.textContent = "Spring" + " " + year;

                const fall_semester = document.createElement('option');
                fall_semester.value = "Fa" + (Number(year) - 1).toString();
                fall_semester.textContent = "Fall" + " " + (Number(year) - 1).toString();

                const summer_semester = document.createElement('option');
                summer_semester.value = "Su" + (Number(year) - 1).toString();
                summer_semester.textContent = "Summer" + " " + (Number(year) - 1).toString();

                semesterFilter.add(spring_semester);
                semesterFilter.add(fall_semester);
                semesterFilter.add(summer_semester);
            }
            else if (month >= 8)
            {
                const fall_semester = document.createElement('option');
                fall_semester.value = "Fa" + year;
                fall_semester.textContent = "Fall" + " " + year;
                            
                const summer_semester = document.createElement('option');
                summer_semester.value = "Su" + year;
                summer_semester.textContent = "Summer" + " " + year;

                const spring_semester = document.createElement('option')
                spring_semester.value = "Sp" + year;
                spring_semester.textContent = "Spring" + " " + year;
                
                semesterFilter.add(fall_semester);
                semesterFilter.add(summer_semester);
                semesterFilter.add(spring_semester);
            }
            else
            {
                const fall_semester = document.createElement('option');
                fall_semester.value = "Fa" + (Number(year) - 1).toString();
                fall_semester.textContent = "Fall" + " " + (Number(year) - 1).toString();

                const summer_semester = document.createElement('option');
                summer_semester.value = "Su" + year;
                summer_semester.textContent = "Summer" + " " + year;

                const spring_semester = document.createElement('option')
                spring_semester.value = "Sp" + year;
                spring_semester.textContent = "Spring" + " " + year;

                            
                semesterFilter.add(summer_semester);
                semesterFilter.add(spring_semester);
                semesterFilter.add(fall_semester);
            }
            const more_semesters = document.createElement('option')
            more_semesters.value = "MoreSemesters";
            more_semesters.textContent = "More Semesters";
            semesterFilter.add(more_semesters);
        }

        addInitialSemesters()
                async function addClassesToGrid(semester_input)
        {
            if (semester_input== "MoreSemesters")
            {
                //Remove MoreSemesters Options
                const semesterFilter = document.getElementById('semesterFilter');
                semesterFilter.remove(semesterFilter.selectedIndex);

                //Add Previous semesters into the options
                const lastOption = semesterFilter.options[semesterFilter.options.length - 1].value;
                let season = lastOption.substr(0, 2);
                let year = Number(lastOption.substr(2, 6));

                for(let i = 0; i < 25; i++)
                {
                    let lastSeason = "";
                    if (season == "Fa")
                    {
                        lastSeason = "Summer";
                        season = "Su";
                    }
                    else if (season == "Su")
                    {
                        lastSeason = "Spring";
                        season = "Sp";
                    }
                    else
                    {
                        lastSeason = "Fall";
                        season = "Fa";
                        year = year - 1;    
                    }
                    let option = document.createElement('option');
                    option.value = lastSeason.substr(0,2) + year.toString();
                    option.textContent = lastSeason + " " + year.toString();
                    semesterFilter.add(option);
                }
            }
            else
            {
                let semester = ({ Sp: "Spring", Fa: "Fall", Su: "Summer"})[semester_input.substr(0,2)];
                let year = semester_input.substr(2,4);

                //Do Canvas Stuff
                const storeBody = new FormData();
                storeBody.append('action', 'fetch-classes-from-semester');
                storeBody.append('csrf_token', csrfToken);
                storeBody.append('term', year+" "+semester);
                //storeBody.append('term-without-space', year+semester);

                const storeRes = await fetch('api-proxy.php', { method: 'POST', body: storeBody });
                const storeData = await storeRes.json();
                if (storeData.next_csrf) csrfToken = storeData.next_csrf;
                if (!storeData.success) {
                    showError(storeData.message || 'Failed to get semester courses.');
                    return;
                }

                let grid_classes = document.getElementById('classesGrid')
                
                //Remove previous cards when clicking on a new semester
                if (grid_classes.hasChildNodes())
                {
                    grid_classes.replaceChildren()
                }

                // Reset select all
                const selectAll = document.getElementById('selectAll');
                if (selectAll) selectAll.checked = false;

                function updateSelectAllState() {
                    const allCbs = document.querySelectorAll('.card-checkbox');
                    const checkedCbs = document.querySelectorAll('.card-checkbox:checked');
                    if (selectAll) selectAll.checked = (allCbs.length > 0 && allCbs.length === checkedCbs.length);
                    const nextBtn = document.getElementById('next-btn');
                    if (nextBtn) nextBtn.disabled = (checkedCbs.length === 0);
                }

                for (let i = 0; i <  storeData.courses.length; i++)
                {   
                    //Add JSON response to list
                    courses[storeData.courses[i].course_code] = storeData.courses[i];

                    //Add grid to UI
                    let class_card_div = document.createElement('div');
                    class_card_div.className = "class-card"
                    class_card_div.append(createClassHeader(storeData.courses[i]))
                    class_card_div.append(createClassBody(storeData.courses[i]))
                    class_card_div.append(createClassActions(storeData.courses[i]))
                    
                    // Allow clicking whole card to toggle checkbox
                    class_card_div.addEventListener('click', function(e) {
                        // Prevent triggering twice if they click directly on the checkbox
                        if (e.target.classList.contains('card-checkbox') || e.target.classList.contains('action-btn')) return;
                        const cb = this.querySelector('.card-checkbox');
                        if(cb) {
                            cb.checked = !cb.checked;
                            cb.dispatchEvent(new Event('change'));
                        }
                    });

                    // Sync styling when checkbox changes
                    const cb = class_card_div.querySelector('.card-checkbox');
                    if (cb) {
                        cb.addEventListener('change', function() {
                            if (this.checked) class_card_div.classList.add('selected-card');
                            else class_card_div.classList.remove('selected-card');
                            updateSelectAllState();
                        });
                    }

                    grid_classes.append(class_card_div)
                }

                //If the input loads classes, then make the next-page btn visible and turn off warnings
                if (grid_classes.hasChildNodes())
                {
                  const selectionBar = document.getElementById("selection-bar")
                  if (selectionBar) selectionBar.style.display = "flex"
                  const warning_text = document.getElementById('warning_next_page')
                  if (warning_text) warning_text.style.display = "none"
                }
                else
                {
                  const selectionBar = document.getElementById("selection-bar")
                  if (selectionBar) selectionBar.style.display = "none"
                  const warning_text = document.getElementById('warning_next_page')
                  if (warning_text) {
                      warning_text.textContent = "No Classes Found"
                      warning_text.style.display = "block"
                  }
                }
          }
        }
        const semesterFilter = document.getElementById('semesterFilter');
        semesterFilter.addEventListener("change", (e) => addClassesToGrid(e.target.value))
        addClassesToGrid(semesterFilter.options[0].value)


    </script>

</body>
</html>