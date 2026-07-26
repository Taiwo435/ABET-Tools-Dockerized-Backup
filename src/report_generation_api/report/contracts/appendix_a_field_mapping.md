# Appendix A Contract Field Mapping

Contract version: `1.0`

The report generator consumes this contract as plain data. It does not import or
depend on Symfony entities. The database adapter is responsible for converting
stored syllabus rows into the contract.

| Contract field | Status | Stored source | Transformation or gap |
| --- | --- | --- | --- |
| `course_code` | Derived | `course_subject`, `course_number` | Join with one space, for example `CSE 423`. |
| `course_name` | Required | `course_name` | Trim surrounding whitespace. |
| `credits` | Required | `credits` | Must be a positive number. |
| `contact_hours` | Required | `contact_hours` | Trim surrounding whitespace. |
| `credit_category` | Required | `credit_categorization` | Rename for the contract. |
| `delivery_type` | Missing from workflow | None | The database adapter emits `unspecified` until the syllabus workflow stores `in_person`, `online`, or `hybrid`. |
| `instructors` | Required | `instructor_name` JSON | Decode as a non-empty list of names. |
| `textbooks` | Optional | `textbook` JSON | Decode as a list; an empty list is valid. |
| `catalog_description` | Required | `catalog_description` | Trim surrounding whitespace. |
| `prerequisites` | Optional | `prerequisites` | Empty text is valid. |
| `course_type` | Required | `course_type` | `R`, `E`, or `SE`. |
| `specific_goals` | Required | `specific_goals` JSON | Decode as a non-empty list. |
| `student_outcomes` | Required | `student_outcomes` JSON | Decode as a non-empty list. |
| `topics_covered` | Required | `topics_covered` JSON | Decode as a non-empty list. |

The root `schema_version` is required and must be `1.0`. The root `courses`
array supports more than one course, and each course carries its own delivery
type so mixed-delivery reports are valid.
