# Testing Default From Query

This module has no automated test suite. Use the steps below to verify correct behavior on a live REDCap instance.

## Prerequisites

- The module is installed and enabled at the system level (see README.md).
- You have REDCap Admin access.

---

## Test 1: Auto-incrementing visit number within a single project

This test verifies that the `next_visitnum` query correctly suggests the next sequential visit number for a record, starting at 1 for new records and incrementing with each saved visit.

### Setup

1. In REDCap, create a new project by importing `examples/DefaultFromQueryTest.REDCap.xml` via **New Project > Upload a REDCap project XML file**.
2. Enable the Default From Query module for this project via **Manage External Modules**.
3. Open the module's project configuration and add a query entry:
   - **Query Name:** `next_visitnum`
   - **SQL:** paste the contents of `examples/one_project.sql`
   - Leave additional project ID fields blank.
4. Save the configuration.

### Test 1a: New record starts at visit number 1

1. Create a new record in the project.
2. Open the **Header** form for the first instance.
3. Verify that the visit number field is pre-populated with **1**.

### Test 1b: Second visit for an existing record suggests visit number 2

1. Open the project and navigate to the **Record Home Page** for an existing record (e.g., record `1`).
2. Add a new instance of the repeating instrument by clicking **Add new**.
3. Open the **Header** form for the new instance.
4. Verify that the visit number field is pre-populated with **2**.
5. Fill in any required fields and save the form.

### Test 1c: Visit number increments on subsequent visits

1. Repeat the steps in Test 1b — add another new instance for the same record and open the **Header** form.
2. Verify the suggested visit number is now **3**.
3. Repeat as desired; the suggested value should increment by 1 each time.

---

## Test 2: Auto-incrementing visit number across two projects

This test verifies that the `next_visitnum` query correctly suggests the next sequential visit number when visit history spans two REDCap projects. The suggested visit number must be one greater than the highest visit number found in either project.

### Setup

This test builds on the project created in Test 1 (referred to below as **Project 1**).

1. Create a second REDCap project by importing `examples/DefaultFromQueryTest.REDCap.xml` again via **New Project > Upload a REDCap project XML file**. This is **Project 2**.
2. Note the **Project ID** of Project 2 (visible next to the project title).
3. In **Project 2**, navigate to the **Record Home Page** for record `102`.
4. Open an existing instance of the **Header** form (or create one if none exists) and manually set the visit number to **5**. Save the form.

### Reconfigure the query in Project 1

1. In **Project 1**, open the Default From Query module configuration.
2. Edit the `next_visitnum` query entry:
   - **SQL:** replace the existing SQL with the contents of `examples/two_projects.sql`
   - **Additional Project ID 1 (pid1):** enter the Project ID of **Project 2**
3. Save the configuration.

### Test 2a: Visit number for record 102 reflects the maximum across both projects

1. In **Project 1**, navigate to the **Record Home Page** for record `102`.
2. Confirm that any existing instances of the **Header** form show only visit numbers lower than **5**.
3. Add a new instance of the repeating instrument by clicking **Add new**.
4. Open the **Header** form for the new instance.
5. Verify that the visit number field is pre-populated with **6** — one greater than the value of **5** recorded in Project 2.
