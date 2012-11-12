Parts of the CampusConnect plugins set:

local_campusconnect.zip - the main part of the code (including the settings UI)
block_campusconnect.zip - the block that needs to be added to a course to allow the exporting of the course to other sites
auth_campusconnect.zip - handles the authentication of users arriving from a remote system via a course link
enrol_campusconnect.zip - handles the enrolment of users in courses via 'coursemembers' requests
course.zip - a sing;le file to add to the 'course' folder to enable courselink functionality

Note each file needs to be unzipped into the folder indicated by the first half of its name.

e.g.
local_campusconnect => [moodlecode]/local/campusconnect
auth_campusconnect => [moodlecode]/auth/campusconnect
etc.

