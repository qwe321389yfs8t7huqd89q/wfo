async function login_check() {
    let response;
    response = await axios.get(`api/check`).then(response => {
        return true;
    }).catch(error => {
        return false;
    });
    return response;
}

async function register_check() {
    let response;
    response = await axios.get(`api/register`).then(response => {
        return false;
    }).catch(error => {
        return true;
    });
    return response;
}


function set_month_target(year, month, target) {
    axios.post(`api/target/year/${year}/month/${month}/target/${target}`).then(response => {
        return true;
    }).catch(error => {
        return false;
    });
}

function set_working_days(year, month, target) {
    axios.post(`api/working-days/year/${year}/month/${month}/working-days/${target}`).then(response => {
        return true;
    }).catch(error => {
        return false;
    });
}

function set_year_target(year, target) {
    axios.post(`api/target/year/${year}/target/${target}`).then(response => {
        return true;
    }).catch(error => {
        return false;
    });
}


async function generate_commands() {
    let response;
    response = await axios.get(`api/generate-commands`).then(response => {
        txt = "";
        response.data.data.forEach((el) => {
            if (el !== undefined) {
                txt += el + "<br>";
            }
        })
        document.getElementById("generatedCommandsBody").innerHTML = txt;
        return null;
    }).catch(error => {
        return null;
    });
}

async function get_tokens() {
    let response;
    document.getElementById("existing-tokens-list").innerHTML = ""; // Clear existing list
    response = await axios.get(`api/get-tokens`).then(response => {
        response.data.data.forEach((el) => {
            if (el !== undefined) {
                document.getElementById("existing-tokens-list").innerHTML += "<div> ID: " + el.id + ", Name: " + el.token_name + "&nbsp;<button class=\"btn btn-danger btn-sm\" onclick=\"deleteToken(" + el.id + ")\">Delete</button></div>"; // Append to existing list
            }
        })
        return null;
    }).catch(error => {
        return null;
    });
}

async function deleteToken(token_id) {
    let response;
    response = await axios.post(`api/revoke-token`, { token_id: token_id }).then(response => {
        alert("Token deleted successfully. Please refresh the page to see the updated list.");
        get_tokens();
        return true;
    }).catch(error => {
        alert("Error deleting token.");
        return null;
    });
}

async function newToken(element, token_name_input_id) {
    if (document.getElementById(token_name_input_id).value.trim() === "") {
        alert("Please enter a token name.");
        return null;
    }
    let response;
    response = await axios.post(`api/generate-token`, { token_name: document.getElementById(token_name_input_id).value }).then(response => {
        document.getElementById(element).value = response.data.data.token;
        get_tokens();
        return true;
    }).catch(error => {
        return null;
    });
}


async function get_settings() {
    try {
        const resp = await axios.get(`api/get-settings`);
        const data = resp.data && resp.data.data ? resp.data.data : resp.data;
        // normalize days array
        const days = Array.isArray(data.days_to_show) ? data.days_to_show : [];

        let r = {};
        r.days = days;
        r.language = data.language || null;
        return r;
    } catch (error) {
        return null;
    }
}

async function populate_settings_in_modal() {
    try {
        const data = await get_settings();

        const days = data.days || [];

        // uncheck all first
        document.querySelectorAll('#settings-days-to-show input[type=checkbox]').forEach(el => el.checked = false);
        // check those returned
        days.forEach(d => {
            const el = document.querySelector(`#settings-days-to-show input[value="${d}"]`);
            if (el) el.checked = true;
        });

        // set language select
        if (data.language) {
            const langEl = document.getElementById('settings-language');
            if (langEl) langEl.value = data.language;
        }
    } catch (error) {
        return null;
    }
}

async function save_settings() {
    try {
        const checked = Array.from(document.querySelectorAll('#settings-days-to-show input[type=checkbox]:checked'))
            .map(el => el.value);
        const language = document.getElementById('settings-language') ? document.getElementById('settings-language').value : null;

        await axios.post(`api/save-settings`, {
            days_to_show: checked,
            language: language
        });
        // optional: give feedback
        return true;
    } catch (error) {
        return null;
    }
}

function generate_hidden_days(days) {
    if (!days || days.length === 0) {
        return [];
    }
    const predefined_days = { 0: "sun", 1: "mon", 2: "tue", 3: "wed", 4: "thu", 5: "fri", 6: "sat" };
    let hidden_days = [];
    for (const [i, value] of Object.entries(predefined_days)) {
        if (!days.includes(value)) {
            hidden_days.push(parseInt(i));
        }
    };
    return hidden_days;
}

function update_stats(year, month) {
    const month_target = document.getElementById('month-target');
    const month_target_progressbar = document.getElementById('month-target-progressbar');
    const year_target = document.getElementById('year-target');
    const year_target_progressbar = document.getElementById('year-target-progressbar');
    const working_days = document.getElementById('working-days');
    const holidays = document.getElementById('holidays');
    const sickleave = document.getElementById('sickleave');
    const overtime = document.getElementById('overtime');
    const office_min = document.getElementById('office-actual-min');

    const month_target_edit = document.getElementById('month-target-edit');
    const year_target_edit = document.getElementById('year-target-edit');
    const working_days_edit = document.getElementById('working-days-edit');

    let calc = 0;
    let calc_year = 0;
    axios.get(`api/target/year/${year}/month/${month}`).then(response => {
        month_target.innerText = response.data.data.month_target !== null ? response.data.data.month_target : "100";
        month_target_edit.value = month_target.innerText;
        year_target.innerText = response.data.data.year_target !== null ? response.data.data.year_target : "100";
        year_target_edit.value = year_target.innerText;
        working_days.innerText = response.data.data.working_days !== null ? response.data.data.working_days : "-";
        working_days_edit.value = working_days.innerText;
        holidays.innerText = response.data.data.holidays !== null ? response.data.data.holidays : "-";
        sickleave.innerText = response.data.data.sickleave !== null ? response.data.data.sickleave : "-";
        overtime.innerText = response.data.data.overtime !== null ? response.data.data.overtime + "h" : "-";
        if (response.data.data.working_days !== null
            && response.data.data.office_days !== null
            && response.data.data.overtime_office_only !== null
            && response.data.data.month_target !== null) {

            calc = ((response.data.data.office_days + (response.data.data.overtime_office_only / 8)) / (((response.data.data.working_days - (response.data.data.holidays + response.data.data.sickleave)) * response.data.data.month_target) / 100)) * 100;
            office_min.innerText = (response.data.data.office_days + (response.data.data.overtime_office_only / 8)) + "/" + (((response.data.data.working_days - (response.data.data.holidays + response.data.data.sickleave)) * response.data.data.month_target) / 100);
        }

        if (response.data.data.working_days_year !== null
            && response.data.data.office_days_year !== null
            && response.data.data.overtime_year_office_only !== null
            && response.data.data.year_target !== null) {

            calc_year = ((response.data.data.office_days_year + (response.data.data.overtime_year_office_only / 8)) / (((response.data.data.working_days_year - (response.data.data.holidays_year + response.data.data.sickleave_year)) * response.data.data.year_target) / 100)) * 100;
        }

        if (calc >= 100) {
            month_target_progressbar.classList.add("bg-success");
        } else {
            month_target_progressbar.classList.remove("bg-success");
        }
        if (calc >= 0 && calc <= 50) {
            month_target_progressbar.classList.add("bg-warning");
        } else {
            month_target_progressbar.classList.remove("bg-warning");
        }
        month_target_progressbar.style.width = `${calc}%`;

        if (calc_year >= 100) {
            year_target_progressbar.classList.add("bg-success");
        } else {
            year_target_progressbar.classList.remove("bg-success");
        }
        if (calc_year >= 0 && calc_year <= 50) {
            year_target_progressbar.classList.add("bg-warning");
        } else {
            year_target_progressbar.classList.remove("bg-warning");
        }
        year_target_progressbar.style.width = `${calc_year}%`;

    }).catch(error => {
        month_target.innerText = "No data";
    });
}


async function populate_stats_in_modal() {
    try {
        const stats = ["month-target",
            "working-days",
            "office-actual-min",
            "holidays",
            "sickleave",
            "overtime",
        ];
        for (const stat of stats) {
            const el = document.getElementById(`${stat}`);
            const targetEl = document.getElementById(`stats-${stat}`);
            if (el && targetEl) {
                targetEl.innerText = el.innerText;
            }
        }
        const statsTitleEl = document.getElementById('stats-title');
        statsTitleEl.innerText = calendar.getCurrentData().viewTitle;
        return true;
    } catch (error) {
        return null;
    }
}

async function save_map(map, name, imageBoundsX, imageBoundsY, type) {
    try {
        const response = await axios.post('api/map', { map: map, name: name, imageBoundsX: imageBoundsX, imageBoundsY: imageBoundsY, type: type });
        return response.data;
    } catch (error) {
        console.error("Error saving map:", error);
        return null;
    }
}

async function delete_map(map_id) {
    try {
        const response = await axios.post('api/map/delete', { map_id: map_id });
    } catch (error) {
        console.error("Error deleting map:", error);
    }

    await populate_maps_list();
}

async function upload_map() {
    const map = document.getElementById("map-file").files[0];
    const name = document.getElementById("map-name").value;
    const imageBoundsX = parseFloat(document.getElementById("map-imageBoundsX").value);
    const imageBoundsY = parseFloat(document.getElementById("map-imageBoundsY").value);
    const type = document.getElementById("map-type").value;

    if (!map) {
        alert("Please select a file.");
        return;
    }

    if (map.type !== "image/png") {
        alert("Please select a PNG file.");
        return;
    }

    const reader = new FileReader();
    reader.onload = async function (event) {
        const base64String = event.target.result.split(',')[1];
        const result = await save_map(base64String, name, imageBoundsX, imageBoundsY, type);
        if (result) {
            alert("Map uploaded successfully!");
        } else {
            alert("Error uploading map.");
        }
    };
    reader.readAsDataURL(map);

    await populate_maps_list();
}

async function get_maps() {
    try {
        const response = await axios.get('api/maps/info');
        return response.data.data;
    } catch (error) {
        console.error("Error getting maps:", error);
        return null;
    }
}

async function get_recent_seats() {
    try {
        const response = await axios.get('api/seats/recent/office');
        return response.data.data;
    } catch (error) {
        console.error("Error getting maps:", error);
        return null;
    }
}

async function get_recent_spots() {
    try {
        const response = await axios.get('api/seats/recent/parking');
        return response.data.data;
    } catch (error) {
        console.error("Error getting maps:", error);
        return null;
    }
}

async function populate_maps_list() {
    try {
        const maps = await get_maps();
        const mapsListEl = document.getElementById('maps-list');
        mapsListEl.innerHTML = '';
        maps.forEach(map => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.innerHTML = `
            <div class="container-fluid mb-2">
                <div class="row">
                    <div class="col-3">
                        <span>${map.name}</span>
                    </div>
                    <div class="col-6">
                        <input class="form-control form-control-sm" type="file" id="seats-file-${map.id}" accept=".json" />
                    </div>
                    <div class="col-3">
                        <div class="btn-toolbar justify-content-end" role="toolbar" aria-label="Toolbar operations">
                            <div class="btn-group me-2" role="group" aria-label="add-seats">
                                <button class="btn btn-primary btn-sm" onclick="confirm('Are you sure you want to add seats from the selected file? Existing seats will be overwritten!') && bulk_create_seats(${map.id})">Add seats</button>
                            </div>
                            <div class="btn-group" role="group" aria-label="operations">
                                <button class="btn btn-primary btn-sm" onclick="show_map(${map.id})">Show</button>
                                <button class="btn btn-danger btn-sm" onclick="delete_map(${map.id})">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;
            mapsListEl.appendChild(li);
        });
    } catch (error) {
        console.error("Error populating maps list:", error);
    }
}

function show_map(map_id) {
    window.open(`map.html?id=${map_id}`, '_blank');
}

async function show_map_today(type = 'office') {
    const today_el = document.getElementById("today-seat");
    const map_id = today_el.dataset[`${type}_map_id`];
    const seat = today_el.dataset[`${type}_seat`];
    if (seat == undefined || map_id == undefined) {
        window.open(`map.html?id=${map_id}`, '_blank');
    } else {
        window.open(`map.html?id=${map_id}&seat_id=${seat}`, '_blank');
    }
}

async function get_map_today() {
    const today = new Date();
    const today_el = document.getElementById("today-seat");
    const default_maps = { "parking": null, "office": null };
    try {
        const maps = await get_maps();
        maps.forEach(map => {
            if (map.type == 'office') {
                default_maps.office = map.id;
            }
            if (map.type == 'parking') {
                default_maps.parking = map.id;
            }
        });
    } catch (error) {
        console.error(error);
        return null;
    }
    try {
        const response = await axios.get(`api/seat/booked?date=${today.toISOString().split('T')[0]}`);
        results = response.data.data;

        if (results.result) {
            const map_id = results.result.map_id;
            const seat = results.result.id;
            today_el.dataset.office_map_id = map_id;
            today_el.dataset.office_seat = seat;
        } else {
            today_el.dataset.office_map_id = default_maps.office;
            delete today_el.dataset.office_seat;
        }
    } catch (error) {
        delete today_el.dataset.office_map_id;
        delete today_el.dataset.office_seat;
        console.error(`Error getting seats for map ${map_id}:`, error);
        return null;
    }
    try {
        const response2 = await axios.get(`api/spot/booked?date=${today.toISOString().split('T')[0]}`);
        results2 = response2.data.data;

        if (results2.result) {
            const map_id2 = results2.result.map_id;
            const seat2 = results2.result.id;
            today_el.dataset.parking_map_id = map_id2;
            today_el.dataset.parking_seat = seat2;
        } else {
            today_el.dataset.parking_map_id = default_maps.parking;
            delete today_el.dataset.parking_seat;
        }
    } catch (error) {
        delete today_el.dataset.parking_map_id;
        delete today_el.dataset.parking_seat;
        console.error(`Error getting seats for map ${map_id}:`, error);
        return null;
    }
}

window.addEventListener(
    "load",
    function () {
        (async () => {
            await get_map_today();
        })();
    }
);

async function get_map_seats(map_id) {
    try {
        const response = await axios.get(`api/map/${map_id}/seats`);
        return response.data.data;
    } catch (error) {
        console.error(`Error getting seats for map ${map_id}:`, error);
        return null;
    }
}

async function create_seat(map_id, name, description, bookable, x, y) {
    try {
        const response = await axios.post('api/seat', {
            map_id: map_id,
            name: name,
            description: description,
            bookable: bookable,
            x: x,
            y: y
        });
        return response.data;
    } catch (error) {
        console.error("Error creating seat:", error);
        return null;
    }
}

async function bulk_create_seats(map_id) {
    try {
        const seats_file_input = document.getElementById(`seats-file-${map_id}`);
        if (!seats_file_input || seats_file_input.files.length === 0) {
            alert("Please select a seats JSON file.");
            return null;
        }

        const file = seats_file_input.files[0];
        const fileContent = await file.text();
        const seats_data = JSON.parse(fileContent);
        const response = await axios.post(`api/seats/bulk/${map_id}`, seats_data);
        if (response.data) {
            alert("Seats added successfully!");
        } else {
            alert("Error adding seats.");
        }
    } catch (error) {
        console.error("Error bulk creating seats:", error);
        return null;
    }
}

async function book(name, day, map_id) {
    if (name != null && name.trim() !== "") {
        axios.post(`api/seat/book-by-name`, {
            reservation_date: day,
            seat_name: name,
            map_id: map_id
        }).then(response => {
            Modal.toggle();
            calendar.refetchEvents();
            get_map_today();
        }).catch(error => {
            logError('There was an error when booking a seat:', error);
        });
    } else {
        alert("Please enter a seat name.");
    }
}

async function delete_booking(day, map_id) {
    axios.post(`api/seat/book-by-name`, {
        reservation_date: day,
        seat_name: "",
        map_id: map_id
    }).then(response => {
        Modal.toggle();
        calendar.refetchEvents();
        get_map_today();
    }).catch(error => {
        logError('There was an error when deleting a seat booking:', error);
    });
}

async function book_seat() {
    const seat_name = document.getElementById('seatSelectionSeatName').value;
    const day = document.getElementById('seatSelectionDay').value;
    const map_id = document.getElementById('seatSelectionMapSelect').value;
    await book(seat_name, day, map_id);
}

async function delete_seat_booking() {
    const day = document.getElementById('seatSelectionDay').value;
    const map_id = document.getElementById('seatSelectionMapSelect').value;
    await delete_booking(day, map_id);
}

async function populate_maps_list_in_seat_selection() {
    try {
        const maps = await get_maps();
        const seatSelectionSeatNameEl = document.getElementById('seatSelectionSeatName');
        seatSelectionSeatNameEl.value = "";
        const mapsListEl = document.getElementById('seatSelectionMapSelect');
        mapsListEl.innerHTML = '';
        maps.forEach(map => {
            if (map.type == 'office') {
                const option = document.createElement('option');
                option.value = map.id;
                option.text = map.name;
                option.selected = true;
                mapsListEl.appendChild(option);
            }
        });
    } catch (error) {
        console.error("Error populating maps list:", error);
    }
}

async function populate_recent_seat_choices() {
    try {
        const recentSeats = await get_recent_seats();
        const recentChoicesEl = document.getElementById('recent-choices');
        recentChoicesEl.innerHTML = '<label>Recent Seats:</label><br>';
        recentSeats.result.forEach(seat => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-secondary btn-sm m-1';
            button.innerText = seat.name;
            button.onclick = () => {
                document.getElementById('seatSelectionSeatName').value = seat.name;
            };
            recentChoicesEl.appendChild(button);
        });

    } catch (error) {
        console.error("Error populating recent seat choices:", error);
    }
}


async function book_parking_spot() {
    const seat_name = document.getElementById('parkingSpotName').value;
    const day = document.getElementById('seatSelectionDay').value;
    const map_id = document.getElementById('parkingSpotMapSelect').value;
    await book(seat_name, day, map_id);
}

async function delete_parking_spot_booking() {
    const day = document.getElementById('seatSelectionDay').value;
    const map_id = document.getElementById('parkingSpotMapSelect').value;
    await delete_booking(day, map_id);
}

async function populate_maps_list_in_parking_spot_selection() {
    try {
        const maps = await get_maps();
        const seatSelectionSeatNameEl = document.getElementById('parkingSpotName');
        seatSelectionSeatNameEl.value = "";
        const mapsListEl = document.getElementById('parkingSpotMapSelect');
        mapsListEl.innerHTML = '';
        maps.forEach(map => {
            if (map.type == 'parking') {
                const option = document.createElement('option');
                option.value = map.id;
                option.text = map.name;
                option.selected = true;
                mapsListEl.appendChild(option);
            }
        });
    } catch (error) {
        console.error("Error populating maps list:", error);
    }
}

async function populate_recent_parking_spot_choices() {
    try {
        const recentSpots = await get_recent_spots();
        const recentChoicesEl = document.getElementById('recent-parking-choices');
        recentChoicesEl.innerHTML = '<label>Recent Spots:</label><br>';
        recentSpots.result.forEach(spot => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-secondary btn-sm m-1';
            button.innerText = spot.name;
            button.onclick = () => {
                document.getElementById('parkingSpotName').value = spot.name;
            };
            recentChoicesEl.appendChild(button);
        });

    } catch (error) {
        console.error("Error populating recent parking spot choices:", error);
    }
}
