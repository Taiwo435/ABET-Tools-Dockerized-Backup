function addTextbook() {
              const container = document.getElementById("textbook-container");

              const div = document.createElement("div");
              div.className = "form-group textbook-row";

              div.innerHTML = `
                  <input type="text" name="textbooks[]" class="form-input" placeholder="Enter textbook">
                  <button type="button" class = "btn btn-remove" style = "margin-top: 3%" onclick="removeRow(this)" class="btn btn-secondary">Remove</button>
              `;

              container.appendChild(div);
          }

          function addTopic() {
              const container = document.getElementById("topics-container");

              const div = document.createElement("div");
              div.className = "form-group topic-row";

              div.innerHTML = `
                  <input type="text" name="topics[]" class="form-input" placeholder="Enter topic">
                  <button type="button" class = "btn btn-remove" style = "margin-top: 3%" onclick="removeRow(this)" class="btn btn-secondary">Remove</button>
              `;

              container.appendChild(div);
          }

          function removeRow(button) {
              const row = button.parentElement;
              const container = row.parentElement;

              // Prevent deleting last remaining row
              if (container.children.length > 1) {
                  row.remove();
              } else {
                  row.querySelector("input").value = "";
              }
          }

          function addCourseOutcome() {
              const container = document.getElementById("course-outcomes-container");
              const div = document.createElement("div");
              div.className = "form-group";
              div.innerHTML = `
                  <input type="text" name="course_outcomes[]" class="form-input" placeholder="Enter course outcome">
                  <button type="button" class = "btn btn-remove" style = "margin-top: 3%" onclick="removeRow(this)" class="btn btn-secondary">Remove</button>
              `;
              container.appendChild(div);
          }
          function addStudentOutcome() {
              const container = document.getElementById("student-outcomes-container");
              const div = document.createElement("div");
              div.className = "form-group";
              div.innerHTML = `
                  <input type="text" name="student_outcomes_addressed[]" class="form-input" placeholder="Enter student outcome addressed">
                  <button type="button" class = "btn btn-remove" style = "margin-top: 3%" onclick="removeRow(this)" class="btn btn-secondary">Remove</button>
              `;
              container.appendChild(div);
          }
        function addCoordinator() {
            const container = document.getElementById("coordinator-container");

            const div = document.createElement("div");
            div.className = "form-group";

            div.innerHTML = `
                <input
                    type="text"
                    name="course_coordinators[]"
                    class="form-input"
                    placeholder="Enter instructor name"
                >
                <button type="button" style = "margin-top: 3%" class = "btn btn-remove" onclick="removeRow(this)" class="btn btn-secondary">
                    Remove
                </button>
            `;

            container.appendChild(div);
        }