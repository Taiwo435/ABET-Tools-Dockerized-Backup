# Canvas Formatting

Python scripts defining the formatting of canvas pages. These depend on folder structures created by the canvas extraction scripts (located in the '/src/extraction_api' folder).

- [Tool 1 Workflow](#tool-1-workflow) - Description of full Tool 1/Canvas Extraction and Formatting workflow.
- [Canvas Formatting Pipeline](#canvas-formatting-pipeline) - Description of full canvas formatting process.
    - [Modules Structure](#modules-structure)
    - [1 - Data Collection (Course Page)](#1---data-collection-course-page)
    - [2 - Data Collection (ABET Page)](#2---data-collection-abet-page)

- [Canvas API documentation](#canvas-api-documentation) - External documentation for Canvas API.
- [Canvas Components](#canvas-components) - Description of all important canvas components for this formatting. Files, folders, modules, pages, etc.
- [Key Modules](#key-modules) - From create_modules.py and create_html.py
    - [canvas_formatting_api.py](#canvas_formatting_apipy)
    - [create_modules.py](#create_modulespy)
    - [create_html.py](#create_htmlpy) 
- [API Endpoints](#api-endpoints) - From canvas_formatting_api.py
    - [POST /generate-html/{course_id}](#post-generate-htmlcourse_id)
    - [POST /format-and-upload/{course_id}](#post-format-and-uploadcourse_id)

## Tool 1 Workflow:
The canvas extraction scripts pull data from specified source course's canvas shells and compiles it into a course folder which is stored in the destination canvas shell under Files. Formatting scripts utilize this folder structure to identify which files need to be placed.  
&emsp;<u>Reasoning</u>: To display links to the files in a canvas page, files need to be located in the course's file directory structure.

_i.e. Relevant assignment data from the CSE 101 canvas shell is pulled into the ABET Review canvas shell and stored as a CSE 101 (2025 Fall) [Smith] folder in the ABET Review's Files section. Formatting scripts then use the files in that folder to display all CSE 101 data in user-friendly modules and pages._

Current canvas files hierarchy for course folders located under Files in Canvas Shell (outlined in fuller detail in the Canvas Extraction API README):
```
Course_name/
├── CSE XXX Course Title (Year Semester) [Instructor(s)] /
│   ├── (Semester Year) /
│       ├── Syllabus /
│           └── all syllabus files located here: syllabus_body.html, syllabus_body.pdf 
│       ├── Test_Assignments /
│           └── Project Evaluations /
│               └── Abet 1 /
│               └── Abet ... /
│               └── all files relevant to the numbered ABET outcome located here.
│           └── Assignments (this is an example assignment group) /
│           └── Quizzes (this is an example assignment group)/
│           └── ... /
│           └── all assignment groups are located here as their own folder
└── Other CSE YYY etc. Course folders are located here
```

> [!NOTE]
> **See for additional information:** [Canvas Extraction API Documentation](/src/extraction_api/README.md)

## Canvas Formatting Pipeline

This sets up the modules and pages inside the chosen destination canvas shell. Following the process of the Extraction API scripts, the extracted data from each source canvas shell is already stored in folders within the destination canvas shell. Formatting takes those course files and data and collects the appropriate data into HTML pages and modules for a better user experience. 

### Modules Structure
There are two types of modules that are currently being uploaded to the canvas shell:
- Assessment Instruments and Student Work Samples
    - CSE-ABET Assessment Instruments and Samples
- Course - Course Folders and Student Work Samples (Semester Year)
    - CSE XXX Course Title (Year Semester) \[Instructors(s)]
    - CSE YYY Course Title (Year Semester) \[Instructors(s)]

The **Assessment Instruments and Student Work Samples** module only has one module item in it (CSE-ABET Assessment Instruments and Samples). This page collects all ABET information for every course and places it in one page. All relevent files are located in the Abet {N} folders in every single course folders' Project Evaluations folder.

- Important sections in the ABET page:
(All sections have their own column in the page's table)
    - _**Student Outcome:**_ ABET outcomes numbered from 1-7, and labeled with the plaintext description of what the current ABET outcome is.
    - _**Assessment Instruments:**_ Includes the ABET report that was generated based on the course information for the given ABET outcome and the HTML description of the assignment which was used to assess the ABET outcome (instrument).
    - _**Student Work Samples:**_ Includes High, Avg/Mid, and Low student samples for each instrument included in the assessment instruments table column.
> [!NOTE]
> The ABET page upload currently adds all ABET relevant data based on those Abet {N} files in all Project Evaluation folders. This means there is no differentiation between Semester Year in this page. This matches current outlines in the CSE ABET Fall 2021 sample canvas shell. If further requirements or requests are made for separate ABET pages based on semester-year or other considerations, changes will need to be made here.

The **Course - Course Folders and Student Work Samples (Semester Year)** module will contain multiple module items: one module item corresponds to one page for each course that took place during that semester-year. Additionally, there will be multiple course folders for every new semester-year that is added.
- Important sections in the Course page: 
    - _**Syllabus and Course Schedule**_: includes the class's syllabus
    - _**Overview of Assignment Groups:**_ lists all relevant assignments, grouping and labeling them based on the Assignment groups which are labeled from the Grades tab of each source canvas shell. Additionally, lists the Project Evaluations (Abet {N}) which are assessed in the course.
    - _**Graded Student Work:**_ Includes a table for each assignment group. Each table has a column for the Assessment, High, Mid, Low, then options for Solution, Marking Guide, and Quiz Statistics. High/mid/low student submissions for each assessment/assignment are listed in the tables.

The full formatting pipeline is outlined in `run_formatting_pipeline()` in `create_modules.py`. Within this pipeline, calls are made to `set_up_course_page` and `set_up_abet_page` from `create_html.py` in order to appropriately set up the two separate HTML pages described above.

### 1 - Data Collection (Course Page)
All course data is identified for the selected course in `run_formatting_pipeline()` within `create_modules.py`. This data collection portion focuses on identifying the correct folder in the destination canvas shell which was created for the chosen source course through the extraction pipeline. Once identified, additional modifications are made to the extracted data to identify specific data which will prove useful in final course page formatting.

_i.e. If the chosen course is CSE 102 and there are three folders in the destination course's Files tab in Canvas, labeled CSE 101..., CSE 102..., and CSE 103..., this collection process will identify the folder_id and all associated files for the CSE 102 folder._
> [!NOTE]
> Currently, the call made to the formatting pipeline in `api-proxy.php` (src/public/Assignments/Grades/api-proxy.php) is encoding the destination id ($destid) as the course_id used in `canvas_formatting_api.py`. This is causing a mismatch in labeling to carry over into the `run_formatting_pipeline()` function within `create_modules.py`. The module and page uploads are still being added to the correct destination course ID that is being passed through, but this mismatch means that the function is mislabeling the $destid as source_course_id. The current implementation is using this mislabeled variable, so any reference to the destination course in that formatting pipeline function is using the `source_course_id` variable.

All canvas formatting occurs within the destination course that was designated to hold the extraction folders.  
1. Find folder structure - Creates a list of all folders in the canvas shell, using pagination. Labels as `file_folders`
    - If a course folder's name is provided, the name is directly matched to identify the selected course's folder based on the name of all folders in the canvas shell.
    - If a course folder's name was not provided, the folder is found using the `find_file_folder()` function along with the course_id, semester, and year.
2. Get grouped files - calls the `get_files()` function to return a dictionary of all files `{"Syllabus": [], "Assignments": []}` based on if they are a syllabus or assignment file and the course name's string.
3. Fetch all files - calls `fetch_all_course_files()` to return a list of every file in the entire canvas shell.
4. Finalize the files dict - labels the dictionary of files as `files`, then adds another key to designate `{"ALL_FILES": all_files}`
5. Pass the `file_folders` and `files` structures to the `set_up_course_page()` function from `create_html.py`.
6. Additional handling of the passed data occurs in `set_up_course_page()` to call `get_assignment_groups()` and build a dictionary of all files in one assignment group with the keys based on the assignment group's name. This function also returns a list of assignment names which are used to build out the tables of assignment groups (Ex: Quizzes, Projects, and Homeworks, might all be different assignment groups in a course). 

> [!NOTE]
> Any modifications to the extraction script's final folder structure will require modification to the formatting scripts, mostly centered here in how the course data is collected and formatted from the Canvas shell. Additionally, some steps in this data collection process may be redundant (i.e. syllabus identification and assignment grouping) and might be able to be simplified.


### 2 - Data Collection (ABET Page)

All course data is identified for the selected course in `run_formatting_pipeline()` within `create_modules.py`. This data collection portion focuses on identifying all files that are included in the Project Evaluations folders for all courses in the destination canvas shell. Specifically, data is pulled and sorted by each Abet {N} folder inside of project evaluation and data is grouped as such.

_i.e. If there are three folders in the destination course's Files tab in Canvas, labeled CSE 101..., CSE 102..., and CSE 103..., this collection process will identify the files located in Abet {N} subfolders for all of these folders._

1. Build ABET course data - Calls the function `_build_abet_data()` in  `run_formatting_pipeline()` within `create_modules.py`. This function returns file_folders_abet, ABET_data, and abet_course_names.
    - file_folders_abet: list of all Project Evaluation folders in the destination canvas shell.
    - ABET_data is in the following format: `{outcome_number (1-7): {course_name: [files]}}`. This allows a list of relevant files to be accessed based on the course name and abet outcome.
        - _i.e. if a course is named CSE 101... and ABET outcome 2 is the desired outcome to reference, all files relevant to ABET outcome 2 from CSE 101... can be accessed using ABET_data[2][CSE 101...]._ 
    - abet_course_names: is a list of all course names which have at least one assignment/assessment instrument which is labeled with an abet outcome.
2. Identify High, Avg/mid, Low student samples along with Assessment Instruments - additional handling based on the structured ABET_data is completed in `set_up_abet_page()` in `create_html.py` using the labeled file names from the extraction pipeline.
> [!NOTE]
> Any modifications to the extraction script's final folder structure will require modification to the formatting scripts, mostly centered here in how the ABET data is collected and formatted from the Canvas shell. Specifically, the handling based on the names will need to be updated to match any new/modified file naming conventions.

## Canvas API documentation
* [Canvas LMS API](https://developerdocs.instructure.com/services/canvas/resources) 

* **API access token:**
Generate at Canvas → Account → Settings → Approved Integrations → New Access Token  
> [!WARNING]
>  This access token is valid for all canvas courses which the user has either TA or Instructor access to. Developers should **never** input destination or source course IDs for real, in-progress canvas shells in which they are TAs or instructors. Running tests on scripts should be restricted to provided/allowed test shells to prevent FERPA violations of current student data.

## Canvas Components
1. **Shell/Course:** A singular canvas shell is the entire canvas course for one class, located in Canvas under Courses. Each course has its own specific `course_id`. This is used to access the course using the Canvas API endpoints or alternatively access the standard course (Requires access to the canvas course).
    
    - This can be found in the url of any course. It is located after _courses_, i.e., `https://canvas.asu.edu/courses/123456`. In this case, the course id would be 123456.
    - The base canvas domain for ASU is set as: `https://canvas.asu.edu`
        - The Canvas API endpoint starts with `/api/v1/courses/:course_id` to specify the course. In `create_modules.py` this is accessed with the `_get_api_base_url()` function.
        - The Base Canvas url for accessing pages within the course itself starts with `/courses/:course_id` to specify the course. In `create_modules.py` this is accessed with the `_get_canvas_base_url()` function.
        - Deciding between using the canvas API or base canvas url depends on the use case. __Example:__ Requests will require the use of the canvas API option, but embedding links in canvas pages will require the use of the base canvas url.

2. **Modules:** A Canvas module is a subsection in a Canvas shell's _Modules_ tab. Each Canvas module can have pages added to it. 
    
    - Each Canvas module can be accessed by adding to the course endpoint with `/courses/:course_id/modules/:module_id`
        - See [Canvas LMS Modules](https://developerdocs.instructure.com/services/canvas/resources/modules) for more information.
    - Canvas modules can be set to either be published or unpublished. Publishing a module will make it visible to everyone listed in the _People_ tab in the course.
    - Each page added to a canvas module will be added as a module item. This endpoint is as follows: `/courses/:course_id/modules/:module_id/items`, see `add_single_module_item()` in `create_modules.py`. Each page is linked as a module item using their page_url. To add the created page as a module item, it must already be created and located in the Canvas Pages tab.
        - Pagination is often required to access all module items: See `get_paginated_list()` in `create_modules.py`.
        - Individual module items are accessed using the item id `/courses/:course_id/modules/:module_id/items/:item_id`

3. **Pages:** A Canvas page is located in a Canvas shell's _Pages_ tab. Pages can additionally be added to modules to allow for easy organization, viewing, and access within the shell.
    - Pages can be accessed by adding to the course endpoint with `/courses/:course_id/pages`. Accessing an individual page is often done using the page url, but can also use the page's id. Using the url is done as follows `/courses/:course_id/pages/:page_url`
        - Pagination is often required here. See `get_paginated_list()` in `create_modules.py`.
    - _Embedding file links:_ links to files in each canvas page are structured in this manner: `https://canvas.adu.edu/courses/:course_id/files/:file_id`
        - An example of this file embedding can be seen in the `set_up_course_page()` function in `create_html.py`. The syllabus_link utilizes this method to embed the syllabus page from the file folder to the user displayed page.
    - See [Canvas LMS Pages](https://developerdocs.instructure.com/services/canvas/resources/pages) for more information

4. **Files/Folders:** Canvas files and folders are both located in the _Files_ tab in a Canvas shell. This behaves like a standard directory structure.

    - Files and folders are accessed using `/courses/:course_id/files` and `/courses/:course_id/folders`.
        - Access to all files located in a specific folder uses the following endpoint: `/courses/:course_id/folders/:folder_id/files`
    - Pagination is required here: see `fetch_all_course_files()` or `get_paginated_list` in `create_modules.py`.
    - _Note:_ regarding duplication, files have an _on\_duplicate_ option to handle renaming or overwriting a file that matches the same name. Folders do not have that same option and require manual handling.
    - See [Canvas LMS Files](https://developerdocs.instructure.com/services/canvas/resources/files) for more information. 
5. **Canvas formatting process:**
    
    - A canvas shell is accessed using its _course\_id_.
    - An HTML page is built using extracted data from the Canvas files/folders that were created with the extraction pipeline.
    - This HTML page is added to the Pages tab in Canvas.
    - If not already created, a module is added to match the course's semester-year.
    - The page is added as a module item to the correct module.
    - If not already published, the module and module item are made public.

## Key Modules

### `canvas_formatting_api.py`

Contains:
- The FastAPI app instance for Canvas Formattng
- All endpoint definitions for create_html.py and create_modules.py

### `create_modules.py`

Parses throught the completed Canvas folder structure to extract relevant student data, then handles the creation of HTML pages and upload to Canvas:
- Includes the `run_formatting_pipeline()` function, defining the full process of fetching data, building HTML, uploading pages, and publishing modules to Canvas.
- Includes `add_page_to_canvas()` to upload the HTML content as a Canvas wiki page - located directly in the Pages tab on Canvas.
- Includes `upload_module_to_canvas()` and `add_single_module_item()` to handle the upload of a Canvas module and the selected page as a module item - located directly in the Modules tab in Canvas.
- Includes `publish_module()` to ensure that the module has been made viewable to anyone with access to the Canvas shell that does not have an Instructor or TA role.

### `create_html.py`

Creates html pages to upload for courses & ABET reports. All conventions are made to match the `CSE ABET Fall 2021` Canvas Shell.
- Includes WriteAbetHtml class
- Includes `set_up_course_page()` to handle creation of the canvas page. The `get_course_html()` function will return the accumulated HTML for the page.
- Includes `set_up_abet_page` to handle creation of the ABET page. The `get_abet_html()` function will return the accumulated HTML for the page.

## API Endpoints

All endpoints are served by FastAPI. The Canvas access token is passed via the `canvas-access-token` HTTP header (never as a query parameter). The `course_id` is passed as a query parameter. 

### `POST /generate-html/{course_id}`

Generates the course page HTML and ABET HTML without uploading to Canvas.
Returns the raw HTML strings for both pages.

| Aspect      | Detail                                       |
| ----------- | -------------------------------------------- |
| Path Params | `course_id` — Canvas course ID               |
| Query Params   | `semester` (optional, default `fall`) |
|                | `year` (optional, default `2023`)      |
|                | `canvas_domain` (optional, default `canvas.asu.edu`)      |
|                | `course_code` (optional, default `""`)      |
|                | `instructor_name` (optional, default `""`)      |
|                | `term_display` (optional, default `""`)      |
| Headers     | `canvas-access-token` (required)             |
| Returns     | `{"course_html": *course-page-html-string*, "abet_html": *abet-page-html-string*}`                            |

---

### `POST /format-and-upload/{course_id}`

Runs the full pipeline for canvas formatting: fetches course data, builds HTML, creates module, uploads the course page and ABET page to Canvas, and publishes the module.

| Aspect         | Detail                                                                |
| -------------- | --------------------------------------------------------------------- |
| Path Params    | `course_id` — Canvas course ID                                       |
| Query Params   | `dest_course_id` (optional) — Course to upload to (defaults to source course)  |
|                | `semester` (optional, default `2023`)      |
|                | `year` (optional, default `2023`)      |
|                | `canvas_domain` (optional, default `canvas.asu.edu`)      |
|                | `course_code` (optional, default `""`)      |
|                | `instructor_name` (optional, default `""`)      |
|                | `course_folder_name` (optional, default `""`) — Exact folder name from extraction (overrides semester/year search)    |
|                | `term_display` (optional, default `""`) — Term display string from extraction (e.g., 'Fall 2023')  |
|                | `overwrite` (optional, default `false`)                                |
| Headers        | `canvas-access-token` (required)                                      |
| Returns        | `{"message": "Formatting and upload complete.", "course_page": *course-page-html-string*, "abet_html": *abet-page-html-string*, "module": *module-object*, "course_name": *course-name-string*}`      |

---
