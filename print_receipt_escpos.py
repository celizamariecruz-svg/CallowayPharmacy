import json
import re
import sys
from datetime import datetime

from escpos.printer import Win32Raw

LINE_WIDTH = 32


def normalize_printer_name(name):
    return re.sub(r"[^a-z0-9]", "", (name or "").lower())


def resolve_printer_name(requested_name):
    requested_name = (requested_name or "").strip()
    if not requested_name:
        return requested_name

    try:
        import win32print

        flags = win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS
        printers = win32print.EnumPrinters(flags)
        available_names = [p[2] for p in printers if len(p) >= 3 and p[2]]

        if requested_name in available_names:
            return requested_name

        wanted_norm = normalize_printer_name(requested_name)
        for candidate in available_names:
            if normalize_printer_name(candidate) == wanted_norm:
                return candidate

        for candidate in available_names:
            if requested_name.lower() in candidate.lower() or candidate.lower() in requested_name.lower():
                return candidate

        return requested_name
    except Exception:
        return requested_name


def wrap_text(text, width):
    text = text.strip()
    if not text:
        return [""]

    lines = []
    while text:
        if len(text) <= width:
            lines.append(text)
            break
        cut = text.rfind(" ", 0, width + 1)
        if cut == -1:
            cut = width
        lines.append(text[:cut].rstrip())
        text = text[cut:].lstrip()
    return lines


def fmt_money(value):
    return f"{value:,.2f}"


def parse_datetime(value):
    if not value:
        return datetime.now()
    try:
        return datetime.fromisoformat(value.replace("Z", "+00:00")).astimezone()
    except ValueError:
        return datetime.now()


def print_receipt(data, printer_name):
    printer = Win32Raw(printer_name)

    receipt_no = data.get("receipt_no", "")
    created_at = parse_datetime(data.get("created_at"))
    cashier = data.get("cashier", "Cashier")
    payment_method = data.get("payment_method", "cash")

    items = data.get("items", [])
    subtotal = sum((i.get("price", 0) * i.get("qty", 0)) for i in items)
    tax = subtotal * 0.12
    total = subtotal + tax
    tendered = float(data.get("amount_tendered", total))
    change = max(0.0, tendered - total)

    printer.set(align="center", width=2, height=2, bold=True)
    printer.text("Calloway Pharmacy\n")
    printer.set(align="center", width=1, height=1, bold=False)
    printer.text("Official Receipt\n")
    printer.text("\n")

    printer.set(align="left")
    printer.text(f"Receipt: {receipt_no}\n")
    printer.text(f"Date: {created_at.strftime('%Y-%m-%d %H:%M')}\n")
    printer.text(f"Cashier: {cashier}\n")
    printer.text("-" * LINE_WIDTH + "\n")

    for item in items:
        name = str(item.get("name", ""))
        qty = int(item.get("qty", 0))
        price = float(item.get("price", 0))
        line_total = price * qty

        name_lines = wrap_text(name, LINE_WIDTH)
        for idx, line in enumerate(name_lines):
            printer.text(line + "\n")

        left = f"{qty} x {fmt_money(price)}"
        right = fmt_money(line_total)
        printer.text(left.ljust(LINE_WIDTH - len(right)) + right + "\n")

    printer.text("-" * LINE_WIDTH + "\n")

    def total_line(label, value):
        right = fmt_money(value)
        printer.text(label.ljust(LINE_WIDTH - len(right)) + right + "\n")

    total_line("Subtotal", subtotal)
    total_line("VAT 12%", tax)
    printer.set(bold=True)
    total_line("TOTAL", total)
    printer.set(bold=False)
    total_line(f"Paid ({payment_method})", tendered)
    total_line("Change", change)

    printer.text("\nThank you! Get well soon.\n")
    printer.cut()
    printer.close()


def main():
    if len(sys.argv) < 3:
        print("Usage: print_receipt_escpos.py <receipt_json> <printer_name>")
        sys.exit(1)

    receipt_path = sys.argv[1]
    printer_name = resolve_printer_name(sys.argv[2])

    with open(receipt_path, "r", encoding="utf-8") as f:
        data = json.load(f)

    if not printer_name:
        print("Printer name is required")
        sys.exit(1)

    print_receipt(data, printer_name)


if __name__ == "__main__":
    main()
