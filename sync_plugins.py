#!/usr/bin/env python3
"""Synchronize Dali Moodle plugins from the canonical repository."""

from pathlib import Path
import argparse
import filecmp
import shutil

ROOT = Path(__file__).resolve().parent
TARGETS = {
    "moodle41": ROOT.parent / "moodle41",
    "moodle5": ROOT.parent / "moodle5" / "public",
}
PLUGINS = (
    "local/aigrading",
    "local/ailessonplan",
    "local/aiquizgen",
    "local/daliwidget",
    "local/quiz_stats_cache",
    "local/siteframe",
    "blocks/siteframe",
    "mod/siteframe",
    "mod/quiz/accessrule/webcamguard",
    "mod/quiz/report/lightstats",
)


def equal(source: Path, target: Path) -> bool:
    comparison = filecmp.dircmp(source, target)
    return not (comparison.left_only or comparison.right_only or comparison.diff_files or comparison.funny_files) and all(
        equal(source / name, target / name) for name in comparison.common_dirs
    )

def promote(source: Path, target: Path, dry_run: bool) -> None:
    print(f"{'Would promote' if dry_run else 'Promoting'} {source} -> {target.relative_to(ROOT)}")
    if not dry_run:
        shutil.rmtree(target, ignore_errors=True)
        target.mkdir(parents=True, exist_ok=True)
        shutil.copytree(source, target, dirs_exist_ok=True, ignore=shutil.ignore_patterns(".git"))


def sync(source: Path, target: Path, dry_run: bool) -> bool:
    if target.is_dir() and equal(source, target):
        return False
    print(f"{'Would sync' if dry_run else 'Syncing'} {source.relative_to(ROOT)} -> {target}")
    if not dry_run:
        shutil.rmtree(target, ignore_errors=True)
        target.mkdir(parents=True, exist_ok=True)
        shutil.copytree(source, target, dirs_exist_ok=True, ignore=shutil.ignore_patterns(".git"))
    return True


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("targets", nargs="*", choices=TARGETS, default=list(TARGETS))
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--promote-moodle41", action="store_true")
    args = parser.parse_args()

    if args.promote_moodle41:
        for plugin in ("local/aigrading", "mod/quiz/accessrule/webcamguard"):
            promote(TARGETS["moodle41"] / plugin, ROOT / plugin, args.dry_run)

    missing = [plugin for plugin in PLUGINS if not (ROOT / plugin).is_dir()]
    if missing:
        parser.error("missing canonical plugins: " + ", ".join(missing))

    changed = sum(
        sync(ROOT / plugin, TARGETS[target] / plugin, args.dry_run)
        for target in args.targets
        for plugin in PLUGINS
    )
    print(f"{changed} plugin copies {'would change' if args.dry_run else 'changed'}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
