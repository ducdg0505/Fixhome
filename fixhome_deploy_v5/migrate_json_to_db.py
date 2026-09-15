from __future__ import annotations

import argparse
import json
from pathlib import Path

from fixhome.db import SessionLocal, _write_full_state, init_db


def main() -> None:
    parser = argparse.ArgumentParser(description="Import dữ liệu FixHome JSON cũ vào database mới")
    parser.add_argument("json_file", help="Đường dẫn db.json cũ")
    args = parser.parse_args()

    path = Path(args.json_file)
    data = json.loads(path.read_text(encoding="utf-8"))
    required = {"users", "companies", "partner_applications", "orders", "complaints", "notifications"}
    missing = required - set(data)
    if missing:
        raise SystemExit(f"Thiếu collection: {', '.join(sorted(missing))}")

    init_db()
    with SessionLocal.begin() as session:
        _write_full_state(session, data)
    print("Import hoàn tất.")


if __name__ == "__main__":
    main()
