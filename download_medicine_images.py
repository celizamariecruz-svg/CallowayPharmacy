#!/usr/bin/env python3
import argparse
import hashlib
import logging
import os
import re
import sqlite3
import sys
from pathlib import Path
from typing import List, Optional, Set, Tuple

from icrawler.builtin import BingImageCrawler

try:
    from PIL import Image
except Exception:
    Image = None

try:
    from dotenv import load_dotenv
except Exception:
    load_dotenv = None


def configure_logging(verbose: bool) -> None:
    level = logging.DEBUG if verbose else logging.INFO
    logging.basicConfig(
        level=level,
        format="[%(asctime)s] %(levelname)s - %(message)s",
        datefmt="%H:%M:%S",
    )


def sanitize_folder_name(name: str, max_length: int = 120) -> str:
    cleaned = re.sub(r'[<>:"/\\|?*\x00-\x1f]', "_", name)
    cleaned = re.sub(r"\s+", " ", cleaned).strip(" .")
    cleaned = cleaned or "unnamed_medicine"
    if len(cleaned) > max_length:
        cleaned = cleaned[:max_length].rstrip(" .")
    return cleaned


def ensure_dir(path: Path) -> None:
    path.mkdir(parents=True, exist_ok=True)


def file_sha256(path: Path, chunk_size: int = 8192) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(chunk_size), b""):
            digest.update(chunk)
    return digest.hexdigest()


def remove_non_image_files(folder: Path) -> None:
    allowed = {".jpg", ".jpeg", ".png", ".webp", ".bmp", ".gif", ".tiff", ".avif"}
    for file in folder.iterdir():
        if file.is_file() and file.suffix.lower() not in allowed:
            try:
                file.unlink(missing_ok=True)
            except Exception:
                pass


def is_valid_resolution(path: Path, min_width: int, min_height: int) -> bool:
    if Image is None:
        return True
    try:
        with Image.open(path) as img:
            w, h = img.size
            return w >= min_width and h >= min_height
    except Exception:
        return False


def resize_to_square(path: Path, size: int = 500) -> bool:
    if Image is None:
        return False
    try:
        with Image.open(path) as img:
            img = img.convert("RGB")
            img = img.resize((size, size), Image.LANCZOS)
            path.with_suffix(".jpg")
            output_path = path.with_suffix(".jpg")
            img.save(output_path, format="JPEG", quality=92, optimize=True)
            if output_path != path:
                path.unlink(missing_ok=True)
        return True
    except Exception:
        return False


def postprocess_downloads(
    folder: Path,
    min_width: int,
    min_height: int,
    resize: bool,
    resize_size: int,
    known_hashes: Set[str],
) -> Tuple[int, int, int]:
    remove_non_image_files(folder)

    kept = 0
    removed_small = 0
    removed_dupe = 0

    for file in sorted(folder.iterdir()):
        if not file.is_file():
            continue

        if not is_valid_resolution(file, min_width, min_height):
            removed_small += 1
            try:
                file.unlink(missing_ok=True)
            except Exception:
                pass
            continue

        if resize:
            resized_ok = resize_to_square(file, resize_size)
            if resized_ok:
                file = file.with_suffix(".jpg")

        try:
            h = file_sha256(file)
        except Exception:
            try:
                file.unlink(missing_ok=True)
            except Exception:
                pass
            continue

        if h in known_hashes:
            removed_dupe += 1
            try:
                file.unlink(missing_ok=True)
            except Exception:
                pass
            continue

        known_hashes.add(h)
        kept += 1

    return kept, removed_small, removed_dupe


def count_images(folder: Path) -> int:
    if not folder.exists():
        return 0
    return sum(1 for f in folder.iterdir() if f.is_file())


def connect_sqlite(db_path: str):
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    return conn


def connect_mysql(host: str, port: int, user: str, password: str, database: str):
    import pymysql

    return pymysql.connect(
        host=host,
        port=port,
        user=user,
        password=password,
        database=database,
        charset="utf8mb4",
        autocommit=True,
    )


def fetch_medicine_names(
    conn,
    db_type: str,
    table: str,
    name_column: str,
    where_clause: Optional[str],
    limit: Optional[int],
) -> List[str]:
    where_sql = f" WHERE {where_clause}" if where_clause else ""
    limit_sql = f" LIMIT {int(limit)}" if limit and limit > 0 else ""
    sql = f"SELECT DISTINCT {name_column} FROM {table}{where_sql}{limit_sql}"

    medicines: List[str] = []

    if db_type == "sqlite":
        cur = conn.cursor()
        cur.execute(sql)
        rows = cur.fetchall()
        for row in rows:
            value = row[0] if not isinstance(row, sqlite3.Row) else row[name_column]
            if value and str(value).strip():
                medicines.append(str(value).strip())
    else:
        with conn.cursor() as cur:
            cur.execute(sql)
            rows = cur.fetchall()
            for row in rows:
                value = row[0]
                if value and str(value).strip():
                    medicines.append(str(value).strip())

    return medicines


def crawl_medicine_images(
    medicine_name: str,
    root_folder: Path,
    max_images: int,
    min_width: int,
    min_height: int,
    skip_existing: bool,
    resize: bool,
    resize_size: int,
    parser_threads: int,
    downloader_threads: int,
) -> None:
    safe_name = sanitize_folder_name(medicine_name)
    medicine_folder = root_folder / safe_name
    ensure_dir(medicine_folder)

    existing_count = count_images(medicine_folder)
    if skip_existing and existing_count >= max_images:
        logging.info("[SKIP] %s | existing=%d", medicine_name, existing_count)
        return

    needed = max(0, max_images - existing_count)
    if needed == 0 and skip_existing:
        logging.info("[SKIP] %s | already complete", medicine_name)
        return

    query = f"{medicine_name} medicine packaging"

    logging.info("[START] %s | existing=%d | target=%d | download_now=%d", medicine_name, existing_count, max_images, max(needed, max_images))

    crawler = BingImageCrawler(
        feeder_threads=1,
        parser_threads=parser_threads,
        downloader_threads=downloader_threads,
        storage={"root_dir": str(medicine_folder)},
    )

    target_download = max_images if not skip_existing else max(needed * 2, needed + 10)

    try:
        crawler.crawl(
            keyword=query,
            max_num=target_download,
            min_size=(min_width, min_height),
            file_idx_offset=0,
        )
    except Exception as ex:
        logging.error("[FAIL] %s | crawl error: %s", medicine_name, ex)
        return

    known_hashes: Set[str] = set()
    kept, removed_small, removed_dupe = postprocess_downloads(
        folder=medicine_folder,
        min_width=min_width,
        min_height=min_height,
        resize=resize,
        resize_size=resize_size,
        known_hashes=known_hashes,
    )

    final_count = count_images(medicine_folder)

    if final_count > max_images:
        files = sorted([f for f in medicine_folder.iterdir() if f.is_file()])
        for f in files[max_images:]:
            try:
                f.unlink(missing_ok=True)
            except Exception:
                pass
        final_count = count_images(medicine_folder)

    logging.info(
        "[DONE] %s | kept=%d | removed_small=%d | removed_dupe=%d | final=%d",
        medicine_name,
        kept,
        removed_small,
        removed_dupe,
        final_count,
    )


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Download medicine images from Bing into per-medicine folders")

    parser.add_argument("--db-type", choices=["sqlite", "mysql"], default=os.getenv("DB_TYPE", "mysql"))

    parser.add_argument("--sqlite-path", default=os.getenv("SQLITE_PATH", "pharmacy.db"))

    parser.add_argument("--mysql-host", default=os.getenv("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.getenv("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.getenv("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.getenv("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-db", default=os.getenv("MYSQL_DB", "calloway"))

    parser.add_argument("--table", default=os.getenv("MED_TABLE", "products"))
    parser.add_argument("--name-column", default=os.getenv("MED_NAME_COLUMN", "name"))
    parser.add_argument("--where", default=os.getenv("MED_WHERE", "type='medicine'"))
    parser.add_argument("--limit", type=int, default=int(os.getenv("MED_LIMIT", "0")))

    parser.add_argument("--output-dir", default=os.getenv("OUTPUT_DIR", "medicine_images"))
    parser.add_argument("--images-per-medicine", type=int, default=int(os.getenv("IMAGES_PER_MEDICINE", "30")))
    parser.add_argument("--min-width", type=int, default=int(os.getenv("MIN_WIDTH", "320")))
    parser.add_argument("--min-height", type=int, default=int(os.getenv("MIN_HEIGHT", "320")))

    parser.add_argument(
        "--skip-existing",
        action=argparse.BooleanOptionalAction,
        default=os.getenv("SKIP_EXISTING", "1") == "1",
    )
    parser.add_argument("--resize", action="store_true", default=os.getenv("RESIZE", "0") == "1")
    parser.add_argument("--resize-size", type=int, default=int(os.getenv("RESIZE_SIZE", "500")))

    parser.add_argument("--parser-threads", type=int, default=int(os.getenv("PARSER_THREADS", "2")))
    parser.add_argument("--downloader-threads", type=int, default=int(os.getenv("DOWNLOADER_THREADS", "4")))

    parser.add_argument("--verbose", action="store_true")

    return parser.parse_args()


def connect_db(args: argparse.Namespace):
    if args.db_type == "sqlite":
        return connect_sqlite(args.sqlite_path)
    return connect_mysql(
        host=args.mysql_host,
        port=args.mysql_port,
        user=args.mysql_user,
        password=args.mysql_password,
        database=args.mysql_db,
    )


def main() -> int:
    if load_dotenv is not None:
        load_dotenv(override=False)

    args = parse_args()
    configure_logging(args.verbose)

    root_folder = Path(args.output_dir)
    ensure_dir(root_folder)

    logging.info("Output root: %s", root_folder.resolve())

    try:
        conn = connect_db(args)
    except Exception as ex:
        logging.error("Database connection failed: %s", ex)
        return 1

    try:
        medicines = fetch_medicine_names(
            conn=conn,
            db_type=args.db_type,
            table=args.table,
            name_column=args.name_column,
            where_clause=args.where,
            limit=args.limit if args.limit > 0 else None,
        )
    except Exception as ex:
        logging.error("Failed to fetch medicine names: %s", ex)
        try:
            conn.close()
        except Exception:
            pass
        return 1

    try:
        conn.close()
    except Exception:
        pass

    if not medicines:
        logging.warning("No medicines found.")
        return 0

    total = len(medicines)
    logging.info("Medicines fetched: %d", total)

    success_count = 0
    fail_count = 0

    for idx, medicine in enumerate(medicines, start=1):
        logging.info("[%d/%d] Processing: %s", idx, total, medicine)
        try:
            crawl_medicine_images(
                medicine_name=medicine,
                root_folder=root_folder,
                max_images=args.images_per_medicine,
                min_width=args.min_width,
                min_height=args.min_height,
                skip_existing=args.skip_existing,
                resize=args.resize,
                resize_size=args.resize_size,
                parser_threads=args.parser_threads,
                downloader_threads=args.downloader_threads,
            )
            success_count += 1
        except KeyboardInterrupt:
            logging.warning("Interrupted by user.")
            break
        except Exception as ex:
            fail_count += 1
            logging.error("[ERROR] %s | %s", medicine, ex)
            continue

    logging.info("Finished. success=%d failed=%d total=%d", success_count, fail_count, total)
    return 0


if __name__ == "__main__":
    sys.exit(main())
