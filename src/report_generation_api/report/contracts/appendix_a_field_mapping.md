# Appendix A Contract Field Mapping

Contract version: `1.0`

The report generator consumes this contract as plain data. It does not import or
depend on Symfony entities. The Symfony `AppendixAReportExportBoundary` is
responsible for selecting eligible revisions and converting them into this
contract before report generation begins. The renderer must not query syllabus
storage or choose between a shared baseline and a course offering.

| Contract field | Status | Canonical lifecycle source | Transformation |
| --- | --- | --- | --- |
| `course_code` | Derived | `CommonCourse` subject and number | Join with one space, for example `CSE 423`. |
| `course_name` | Required | `CommonCourse.courseName` | Emit the selected course title. |
| `credits` | Required | Approved `TemplateRevision` content | Must be a positive number. |
| `contact_hours` | Required | Approved `TemplateRevision` content | Emit canonical `contact_hours`. |
| `credit_category` | Required | Approved `TemplateRevision` content | Emit canonical `credit_category`. |
| `delivery_type` | Required | Selected `CourseOffering`, otherwise `CommonCourse` | Emit `in_person`, `online`, or `hybrid`. |
| `instructors` | Required | Approved `TemplateRevision` content | Emit a non-empty list of names. |
| `textbooks` | Optional | Approved `TemplateRevision` content | An empty list is valid. |
| `catalog_description` | Required | Approved `TemplateRevision` content | Emit canonical `catalog_description`. |
| `prerequisites` | Optional | Approved `TemplateRevision` content | Empty text is valid. |
| `course_type` | Required | Approved `TemplateRevision` content | `R`, `E`, or `SE`. |
| `specific_goals` | Required | Approved `TemplateRevision` content | Emit a non-empty list. |
| `student_outcomes` | Required | Approved `TemplateRevision` content | Emit a non-empty list. |
| `topics_covered` | Required | Approved `TemplateRevision` content | Emit a non-empty list. |

The root `schema_version` is required and must be `1.0`. The root `courses`
array supports more than one course, and each course carries its own delivery
type so mixed-delivery reports are valid. The export boundary rejects
unapproved or incomplete revisions and duplicate selections for one course.
