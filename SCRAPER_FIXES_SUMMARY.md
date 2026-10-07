# Scraper Fixes & Improvements Log

## 1. Fix: Scraper Crashing on Low Result Counts / Small Inputs

### Client Request
The scraper was reported to "not work" or fail when processing files with 2 or less lines of data.

### Diagnosis
- **Root Cause**: The application was encountering `SQLSTATE[HY000] [2002] Connection refused` errors when trying to check the database cache for existing results.
- **The Bug**: The database query code was located *inside* the processing loop but *outside* of the `try-catch` block. This meant that a database connection failure would cause the entire script to crash immediately, leading to a "white screen" or generic error for the user.
- **Secondary Issue**: Helper functions (`parsePrice`, `parseBedrooms`, etc.) were defined inside the `upload` method. This caused "Cannot redeclare function" fatal errors if the method was called more than once (e.g. during testing).

### Actions Taken
1.  **Robust Error Handling**: Moved the `try-catch` block in `PropertyFinderController.php` to wrap the **entire** logic for each client. Now, if the database fails, it catches the error, logs it (or reports it for that specific client), and continues processing the next client instead of crashing the whole process.
2.  **Code Refactoring**: Moved the `parsePrice`, `parseLandSize`, and `parseBedrooms` functions out of the `upload` method and made them private methods of the `PropertyFinderController` class.

---

## 2. Fix: "Invalid District or House Type Mapping" Error

### Client Request
Users reported errors when searching for "detached" in "D15" or "terrace" in "D20".
Error message: `Invalid district or house type mapping`.

### Diagnosis
- **Root Cause**: The mapping logic was **case-sensitive**.
- **The Bug**: The code expected keys like `Detached` (capitalized). When the input was `detached` (lowercase), the lookup failed, returning `null` for the house type ID. This triggered the error message.

### Actions Taken
1.  **Case-Insensitive Mapping**: Updated `PropertyFinderController.php` to convert both the input strings and the map keys to lowercase before comparison.
    - `Detached` / `detached` -> Matches `detached`
2.  **Verification**: Verified with a script that inputs like "TerRaCe" or "d15" now correctly map to their respective IDs.

---

## 3. Fix: `array_filter` Error (Null Cache)

### Client Request
Error: `array_filter(): Argument #1 ($array) must be of type array, null given`.

### Diagnosis
- **Root Cause**: Corrupt database records from previous crashes.
- **The Bug**: The system found a cache record but the JSON file was missing/empty. `json_decode` returned `null`.

### Actions Taken
1.  **Cache Validation**: Added a check in `PropertyFinderController.php`. If `json_decode` returns `null`, the corrupt database record is deleted automatically, and the system fetches fresh data.

---

## 4. Fix: Single Row Files Not Scraping

### Client Request
"File with one or two rows are not scrapped".

### Diagnosis
- **Root Cause**: The code blindly skipped the first row (`$rows->skip(1)`), assuming it was always a header.
- **The Bug**: Single-row files (without headers) were skipped entirely, resulting in 0 clients found.

### Actions Taken
1.  **Smart Header Detection**: Removed `skip(1)`. The code now checks the first row.
    - If it contains "Client Name", it's skipped.
    - Otherwise, it's processed as data.

---

## 5. Fix: "Invalid District or House Type Mapping" (Robust Normalization)

### Client Request
Users reported persistent "Invalid district or house type mapping" errors even when inputs seemed correct (e.g., "D5", "D03").

### Diagnosis
- **Root Cause**: The mapping logic was strictly matching keys in an array.
- **The Bug**:
    - `D5` failed because the map expected `d05`.
    - `D3` failed because it expected `d3` (inconsistent double-digit padding in map keys).
    - `District 15` failed because logic split by `/` only.

### Actions Taken
1.  **Robust Normalization**: Implemented `normalizeDistrict` logic in `PropertyFinderController.php`.
    - Now extracts numbers from strings (e.g., `District 15` -> `15`).
    - Validates range (1-28).
    - Handles multiple formats: `D15`, `d15`, `05`, `5`, `District 5`.
2.  **Flexible House Type Matching**:
    - Added variations map: `semi d` -> `semi-d`, `gcb` -> `good class bungalow`.

---

## 6. Fix: Header Row Processed as Data

### Client Request
"It is showing the first header row if the header is not present in csv... so now it is also bringing the header row".

### Diagnosis
- **Root Cause**: The previous "Smart Header Detection" was too specific.
- **The Bug**: It checked for `Client Name` (Case-Insensitive), but the user's file had `client_name` (snake_case). Since it didn't match, the code treated the header row as a data row, leading to mapping errors when trying to map "district" to a district ID.

### Actions Taken
1.  **Enhanced Header Detection**: Updated `PropertyFinderController.php`.
    - Now checks for `client_name` OR `Client Name`.
    - **Secondary Check**: Also checks if the 2nd column is literally "district" (case-insensitive).
2.  **Verification**: Verified with `test_header_detection.php` that both standard and snake_case headers are correctly skipped.

