def _fetch_all(questionnaire, query, params=()):
    """Run a query and return rows as a list of dictionaries."""
    cursor = questionnaire.db.cursor()
    cursor.execute(query, params)
    rows = cursor.fetchall() or []
    return [dict(row) for row in rows]


def _quantity_total(rows, key="quantity"):
    """Best-effort numeric total for quantity-like columns."""
    total = 0
    for row in rows:
        value = row.get(key)
        if value in (None, ""):
            continue
        try:
            total += int(str(value).strip())
        except (TypeError, ValueError):
            # Some rows may contain non-numeric labels like "N/A".
            continue
    return total


def _x(value):
    """Render truthy DB flags the same way the questionnaire tables do."""
    return "X" if bool(value) else ""


def _group_uto_labs(rows):
    """
    Group UTO lab computers by room while preserving row order.

    The schema stores one row per workstation/printer entry. The questionnaire
    document is organized visually by room, so we expose both the flat list and
    a grouped structure for template flexibility.
    """
    rooms = []
    seen = {}

    for row in rows:
        room_name = (row.get("room_name") or "").strip()
        if room_name not in seen:
            seen[room_name] = {
                "room_name": room_name,
                "entries": [],
                "total_quantity": 0,
            }
            rooms.append(seen[room_name])

        room = seen[room_name]
        room["entries"].append(
            {
                "pc_workstation": row.get("pc_workstation", ""),
                "quantity": row.get("quantity", ""),
            }
        )

        try:
            room["total_quantity"] += int(str(row.get("quantity", "")).strip())
        except (TypeError, ValueError):
            pass

    return rooms


def _build_uto_display_rows(grouped_rooms, grand_total):
    """
    Flatten grouped UTO lab data into the exact row order the questionnaire uses.

    This keeps the DOCX template simple: one loop can render room headings,
    equipment entries, per-room totals, and the final grand total row.
    """
    display_rows = []

    for room in grouped_rooms:
        room_name = room.get("room_name", "")
        if room_name:
            display_rows.append(
                {
                    "row_type": "room",
                    "label": room_name,
                    "quantity": "",
                }
            )

        for entry in room.get("entries", []):
            display_rows.append(
                {
                    "row_type": "entry",
                    "label": entry.get("pc_workstation", ""),
                    "quantity": entry.get("quantity", ""),
                }
            )

        display_rows.append(
            {
                "row_type": "room_total",
                "label": "Total",
                "quantity": room.get("total_quantity", ""),
            }
        )

    display_rows.append(
        {
            "row_type": "grand_total",
            "label": "Total # PCs and Printers for All UTO Sites",
            "quantity": grand_total,
        }
    )

    return display_rows


def _build_scia_lab_display_rows(rows):
    """
    Normalize SCIA lab computer rows for the template.

    The seeded data already contains room headings and total rows. We preserve
    that order and add lightweight row typing so post-render styling can make
    the room rows match the questionnaire.
    """
    display_rows = []

    for row in rows:
        label = row.get("pc_workstations", "")
        quantity = row.get("quantity", "")

        if label and not quantity:
            row_type = "room"
        elif str(label).strip().startswith("Total"):
            row_type = "total"
        else:
            row_type = "entry"

        display_rows.append(
            {
                "row_type": row_type,
                "label": label,
                "quantity": quantity,
            }
        )

    return display_rows


def _build_printer_display_rows(rows):
    """
    Split stored printer rows back into questionnaire-style location + entry rows.

    The DB stores each printer as "location: description". The template in the
    questionnaire shows the location on its own row, followed by the printer row.
    """
    display_rows = []
    current_location = None

    for row in rows:
        printer = str(row.get("printer", "") or "")
        quantity = row.get("quantity", "")
        location, separator, description = printer.partition(": ")

        if separator:
            if location != current_location:
                display_rows.append(
                    {
                        "row_type": "location",
                        "label": location,
                        "quantity": "",
                    }
                )
                current_location = location

            display_rows.append(
                {
                    "row_type": "entry",
                    "label": description,
                    "quantity": quantity,
                }
            )
            continue

        display_rows.append(
            {
                "row_type": "entry",
                "label": printer,
                "quantity": quantity,
            }
        )

    return display_rows


def get_data(questionnaire):
    """
    Fetch and normalize Appendix C equipment data from the DB.

    Returned keys are shaped around the Appendix C template placeholders.
    """
    uto_lab_computers = _fetch_all(
        questionnaire,
        """
        SELECT room_name, pc_workstation, quantity
        FROM uto_lab_computers
        ORDER BY computer_id ASC
        """,
    )

    uto_lab_software_raw = _fetch_all(
        questionnaire,
        """
        SELECT program_name, installed_windows, installed_osx, installed_citrix
        FROM uto_lab_software
        ORDER BY software_id ASC
        """,
    )
    uto_lab_software = [
        {
            "program": row.get("program_name", ""),
            "program_name": row.get("program_name", ""),
            "installed_on_windows": _x(row.get("installed_windows")),
            "version_installed_on_osx": _x(row.get("installed_osx")),
            "version_installed_on_citrix": _x(row.get("installed_citrix")),
        }
        for row in uto_lab_software_raw
    ]

    scia_lab_computers = _fetch_all(
        questionnaire,
        """
        SELECT pc_workstation, quantity
        FROM scia_lab_computers
        ORDER BY scia_computer_id ASC
        """,
    )
    scia_instructional_lab_equipment = [
        {
            "pc_workstations": row.get("pc_workstation", ""),
            "quantity": row.get("quantity", ""),
        }
        for row in scia_lab_computers
    ]

    scia_printers_raw = _fetch_all(
        questionnaire,
        """
        SELECT printer_description, quantity
        FROM scia_printers
        ORDER BY printer_id ASC
        """,
    )
    printers = [
        {
            "printer": row.get("printer_description", ""),
            "quantity": row.get("quantity", ""),
        }
        for row in scia_printers_raw
    ]

    scia_brickyard_software_raw = _fetch_all(
        questionnaire,
        """
        SELECT software_name, version_num, windows_version, linux, macos, vdi_lab
        FROM scia_brickyard_software
        ORDER BY brickyard_software_id ASC
        """,
    )
    scia_brickyard_software_list = [
        {
            "software_name": row.get("software_name", ""),
            "version": row.get("version_num", ""),
            "windows": _x(row.get("windows_version")),
            "linux": _x(row.get("linux")),
            "macos": _x(row.get("macos")),
            "vdi_lab": _x(row.get("vdi_lab")),
        }
        for row in scia_brickyard_software_raw
    ]

    uto_lab_computers_by_room = _group_uto_labs(uto_lab_computers)
    uto_lab_computers_total_quantity = _quantity_total(uto_lab_computers)

    return {
        "uto_lab_computers": uto_lab_computers,
        "uto_lab_computers_by_room": uto_lab_computers_by_room,
        "uto_lab_computers_display_rows": _build_uto_display_rows(
            uto_lab_computers_by_room, uto_lab_computers_total_quantity
        ),
        "uto_lab_computers_total_quantity": uto_lab_computers_total_quantity,
        "uto_lab_software": uto_lab_software,
        "uto_lab_software_total": len(uto_lab_software),
        "scia_instructional_lab_equipment": scia_instructional_lab_equipment,
        "scia_instructional_lab_equipment_display_rows": _build_scia_lab_display_rows(
            scia_instructional_lab_equipment
        ),
        "scia_instructional_lab_equipment_total_quantity": _quantity_total(
            scia_instructional_lab_equipment
        ),
        "printers": printers,
        "printers_display_rows": _build_printer_display_rows(printers),
        "printers_total_quantity": _quantity_total(printers),
        "scia_brickyard_software_list": scia_brickyard_software_list,
        "scia_brickyard_software_total": len(scia_brickyard_software_list),
    }
