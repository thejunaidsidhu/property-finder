import requests
import json

url = "https://www.singaporelanded.com.sg/listings_search"

def test_search(desc, params):
    print(f"\n--- Testing {desc} ---")
    try:
        response = requests.get(url, params=params, timeout=10)
        print(f"Status: {response.status_code}")
        try:
            data = response.json()
            if 'data' in data:
                count = len(data['data'])
                print(f"Result count: {count}")
                if count <= 5:
                    print("Data structure sample (first item):")
                    if count > 0:
                        print(json.dumps(data['data'][0], indent=2))
                    else:
                        print("Data is empty list []")
            else:
                print("KEY 'data' NOT FOUND in response!")
                print("Keys found:", data.keys())
                print("Response dump:", json.dumps(data, indent=2)[:500])
        except json.JSONDecodeError:
            print("Failed to decode JSON. Body head:", response.text[:500])
    except Exception as e:
        print(f"Request failed: {e}")

# Case 1: Likely many results (D19 Terrace)
test_search("Many Results (D19 Terrace)", {
    "route_from": "undefined",
    "districts[]": ["19"],
    "house_type": "1" 
})

# Case 2: Likely few results (D1 Detached)
test_search("Few/Zero Results (D1 Detached)", {
    "route_from": "undefined",
    "districts[]": ["1"], 
    "house_type": "4"
})

# Case 3: Very specific (D28 GCB - likely 0 or 1)
test_search("Sparse Results (D28 GCB)", {
    "route_from": "undefined",
    "districts[]": ["28"],
    "house_type": "5"
})
