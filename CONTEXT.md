# Moodle Plugins

Moodle integrations that expose Dali AI capabilities within a Moodle site.

## Language

**Course Knowledge**:
Knowledge attached to one Moodle course.
_Avoid_: Local knowledge, scoped knowledge

**Global Knowledge**:
Knowledge not attached to any Moodle course.
_Avoid_: Site-wide knowledge, general knowledge

**Knowledge Access Mode**:
The site-level policy determining which Course Knowledge and Global Knowledge an authenticated user may query.
_Avoid_: Scope setting, strict mode

**Course-scoped Mode**:
A Knowledge Access Mode where the active course determines accessible Course Knowledge, while Global Knowledge remains available.
_Avoid_: Local mode, legacy mode

**Site-wide Mode**:
A Knowledge Access Mode where authenticated users may query Course Knowledge from visible courses and Global Knowledge regardless of enrolment; the active course remains the preferred source.
_Avoid_: Global mode, cross-course mode

**Active Course**:
The course whose page currently hosts the widget and whose Course Knowledge has first retrieval priority.
_Avoid_: Current scope, selected course

**Answer Source Policy**:
The site-level policy determining whether answers must be grounded in available knowledge or may fall back to model knowledge.
_Avoid_: AI freedom, external knowledge setting

**Knowledge-only Policy**:
An Answer Source Policy where the assistant refuses unanswered questions when retrieval succeeds without relevant knowledge.
_Avoid_: Strict mode, closed AI

**Knowledge-preferred Policy**:
An Answer Source Policy where the assistant may use model knowledge when retrieval succeeds without relevant knowledge and clearly identifies that fallback.
_Avoid_: Open mode, internet knowledge
