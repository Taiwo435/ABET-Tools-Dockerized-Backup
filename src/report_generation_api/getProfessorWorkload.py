import requests
import json

from typing import Optional, Dict, Any
from urllib3.util.retry import Retry
from requests.adapters import HTTPAdapter
from bs4 import BeautifulSoup
from bs4 import SoupStrainer



BASE_URL = "https://search.asu.edu/api/v1/webdir-profiles/faculty-staff"
PROFILE_BASE = "https://search.asu.edu/profile"


def get_profile_by_asurite(asurite_id: str) -> Optional[Dict[str, Any]]:
    """
    Searches the ASU directory API for a faculty or staff profile based on the provided ASURITE ID.
    
    param asurite_id: The ASURITE ID of the faculty or staff member to search for.
    type asurite_id: str
    return: A dictionary containing the profile information of the faculty or staff member if found, or None if no profile is found or if there is an error during the API request.
    type return: Optional[Dict[str, Any]]
    """
    params = {
        "sort-by": "",
        "query": asurite_id,
        "page": 1,
        "size": 1,
        "client": "asuis",
    }

    headers = {
        "Accept": "application/json",
        "User-Agent": "Mozilla/5.0"
    }

    r = requests.get(BASE_URL, params=params, headers=headers, timeout=30)
    r.raise_for_status()
    data = r.json()

    results = data.get("results", [])
    if not results:
        return None

    return results[0]

def parse_profile_html_page(html_doc: str) -> Optional[Dict[str, Any]]:
    """
    Parses the HTML content of a professor's profile page to extract information about the courses they have taught in different semesters.

    param html_doc: The HTML content of the professor's profile page as a string.
    type html_doc: str
    return: A structured dictionary containing the professor's teaching workload information, or None if the input
    HTML document is invalid or if the necessary information cannot be extracted.
    type return: Optional[Dict[str, Any]]
    """

    data = {
        "terms": []
    }

    if not html_doc:
        return None
    
    #Gets the section of the document which holds course information. 
    strainer = SoupStrainer(id="nav-group-teaching")
    
    filtered_soup = BeautifulSoup(html_doc, "html.parser", parse_only=strainer)

    # Remove all anchor tags as the information is not needed
    for anchor in filtered_soup.find_all("a", href=True):
        anchor.decompose()

    necessaryClassContent = filtered_soup.find_all(["h5", "td"])

    for td in filtered_soup.find_all("td"):
        if td.get_text(strip=True) == "":
            td.decompose()
            
    necessaryClassContent[0].decompose()  # Remove the first h5 tag which is just "Courses"


    for element in necessaryClassContent:
        if element.name == "h5":

            data["terms"].append({
                "term": element.get_text(strip=True),
                "course_numbers": []
            })
        else:
            
            if element.get_text(strip=True) != "":
                data["terms"][-1]["course_numbers"].append(element.get_text(strip=True))

    json_data = json.dumps(data, indent=2)

    #print(json_data)
    return data

def search_for_class_catalog_info_by_title(catalongNbr: str, subject: str) -> Optional[Dict[str, Any]]:
    """
    Searches the ASU course catalog API for information about a specific course based on its catalog number (360) and subject (CSE)

    param catalongNbr: The catalog number of the course (e.g., "360").
    type catalongNbr: str
    param subject: The subject code of the course (e.g., "CSE").
    type subject: str
    return: A dictionary containing information about the course, such as its title and credit hours, or None if the course is not found or if there is an error during the API request.
    type return: Optional[Dict[str, Any]]
    """
    API_URL = "https://eadvs-cscc-catalog-api.apps.asu.edu/catalog-microservices/api/v1/search/courses"

    params = {
        "refine": "Y",
        "campusOrOnlineSelection": "A",
        "catalogNbr": catalongNbr,
        "subject": subject,
        "term": "*"
        
    }

    headers = {
        "Accept": "*/*",
        "Accept-Language": "en-US,en;q=0.9",
        "Authorization": "Bearer null",          # <-- match DevTools literally
        "Origin": "https://catalog.apps.asu.edu",
        "Priority": "u=1, i",
        "Referer": "https://catalog.apps.asu.edu/",
        "User-Agent": "Mozilla/5.0",
    }

    session = requests.Session()

    retry = Retry(
        total=5,
        connect=5,
        read=5,
        backoff_factor=1.0,
        status_forcelist=[403, 429, 500, 502, 503, 504],
        allowed_methods=["GET"],
        respect_retry_after_header=True,
    )
    adapter = HTTPAdapter(max_retries=retry)
    session.mount("https://", adapter)

    resp = session.get(API_URL, params=params, headers=headers, timeout=30)

    #print("Status:", resp.status_code)
    #print("Content-Type:", resp.headers.get("content-type"))

    if resp.status_code != 200:
        print(f"Error: Received status code {resp.status_code} from ASU API")
        return None
    
    if "application/json" in (resp.headers.get("content-type") or ""):
        data = resp.json()
        #print(json.dumps(data, indent=2))
        return data
    

def create_json_reponse(rawjson: Dict) -> Optional[Dict[str, Any]]:
    """
    Creates a structured JSON response based on the raw JSON data extracted from the professor's profile page.

    param rawjson: The raw JSON data extracted from the professor's profile page.
    type rawjson: Dict
    return: A structured JSON response containing the professor's workload information, or None if the input data is invalid.
    type return: Optional[Dict[str, Any]]
    """

    #Allows for the course to be counted
    trackCourses = {}
    cache = {}
    
    response = {

        "semesters": []


    }


    for terms in rawjson["terms"]:

        data = {
            "semester" : "",
            "courses" : []

        }    
    
        print("Still processing")
        data["semester"] = terms["term"]

        for course in terms["course_numbers"]:

            if course in trackCourses:

                trackCourses[course]["amountTaught"] = trackCourses[course]["amountTaught"] + 1

                continue
            else:

                dataForCourse = {
                    "course" : "",
                    "units" : "",
                    "amountTaught" : 0
                }   

                dataForCourse["course"] = course

                splitCourse = course.split(" ")

                if course in cache:
                    dataForCourse["units"] = cache[course]["units"]
                    dataForCourse["amountTaught"] = dataForCourse["amountTaught"] + 1 
                else:

                    dataForCourse["units"] = search_for_class_catalog_info_by_title(splitCourse[1], splitCourse[0])[0]["HOURS"]
                
                    dataForCourse["amountTaught"] = dataForCourse["amountTaught"] + 1 
                    
                #Addes key (Course Number) tied to dataForCourse
                trackCourses[course] = dataForCourse
                cache[course] = dataForCourse
        
                #print(trackCourses[course])


                


        for courses in trackCourses.values():
            
            data["courses"].append(courses)
        
        trackCourses = {}


        response["semesters"].append(data)
    

    #print(json.dumps(response, indent=2))

    return response



def get_professor_workload(asurite_id: str) -> Optional[Dict[str, Any]]:
    """
    Main function to get the professor workload information based on ASURITE ID.
    param asurite_id: The ASURITE ID of the professor.

    type asurite_id: str
    return: A structured JSON response containing the professor's workload information, or None if any step fails.
    type return: Optional[Dict[str, Any]]
    """
    person = get_profile_by_asurite(asurite_id)

    if not person:
        print(f"No profile found for ASURITE ID: {asurite_id}")
        return None

    eid = person.get("eid", {}).get("raw")

    if not eid:
        print(f"No EID found for ASURITE ID: {asurite_id}")
        return None

    url = build_profile_url(eid)
    html_doc = get_profile_by_url(url)

    if not html_doc:
        print(f"Failed to retrieve profile page for EID: {eid}")
        return None

    raw_json = parse_profile_html_page(html_doc)
    if not raw_json:
        print(f"Failed to parse profile page for EID: {eid}")
        return None

    response = create_json_reponse(raw_json)
    return response
    


def build_profile_url(eid: str) -> str:
    """
    Constructs the profile URL for a given EID. The EID is a unique identifier for ASU faculty and staff profiles.
    param eid: The EID of the faculty or staff member.
    type eid: str
    return: The complete URL to access the profile page of the faculty or staff member.
    type return: str
    """
    return f"{PROFILE_BASE}/{eid}"

def get_profile_by_url(url: str) -> str:
    """
    Fetches the HTML content of a profile from the given profile UrL.

    param url: The URL of the profile to fetch.
    type url: str
    return: The HTML content of the profile page as a string. 
    type return: str
    """
    headers = {
        "Accept": "text/html",
        "User-Agent": "Mozilla/5.0"
    }

    r = requests.get(url, headers=headers, timeout=30)
    r.raise_for_status()
    return r.text







if __name__ == "__main__":

    #search_for_class_catalog_info_by_title("CSE 360")


    #person = get_profile_by_asurite("sdosburn")

    #if person:
    #    eid = person.get("eid", {}).get("raw")
    #    url = build_profile_url(eid)

    #    htmlString = get_profile_by_url(url)
    #   raw_json = parse_profile_html_page(htmlString)

    #    create_json_reponse(raw_json)

    #    print("Name:", person.get("display_name", {}).get("raw"))
    #    print("Profile URL:", url)
    
    json_response = get_professor_workload("sdosburn")

    print(json.dumps(json_response, indent=2))

    json_response = get_professor_workload("dchgf")

    print(json.dumps(json_response, indent=2))