import os

def build_tree(path, prefix=""):
    """Fungsi rekursif untuk membangun folder tree yang akurat."""
    tree_lines = []
    
    # Ambil daftar folder dan file .php saja
    try:
        items = sorted([item for item in os.listdir(path) if not item.startswith('.')])
    except PermissionError:
        return []

    # Filter: hanya folder atau file .php
    valid_items = []
    for item in items:
        full_path = os.path.join(path, item)
        if os.path.isdir(full_path) or item.endswith('.php'):
            valid_items.append(item)

    for i, item in enumerate(valid_items):
        is_last = (i == len(valid_items) - 1)
        connector = "└── " if is_last else "├── "
        
        full_path = os.path.join(path, item)
        
        if os.path.isdir(full_path):
            # Jika folder, tambahkan '/' dan telusuri isinya
            tree_lines.append(f"{prefix}{connector}{item}/")
            new_prefix = prefix + ("    " if is_last else "│   ")
            tree_lines.extend(build_tree(full_path, new_prefix))
        else:
            # Jika file
            tree_lines.append(f"{prefix}{connector}{item}")
            
    return tree_lines

def merge_php_to_markdown(output_filename="hasil_dokumentasi.md"):
    root_dir = os.getcwd()
    base_name = os.path.basename(root_dir) or "root"
    
    with open(output_filename, "w", encoding="utf-8") as outfile:
        # 1. Judul & Tree
        outfile.write(f"# Dokumentasi Proyek: {base_name}\n\n")
        outfile.write("## Struktur Folder\n")
        outfile.write("```text\n")
        outfile.write(f"{base_name}/\n")
        
        tree_structure = build_tree(root_dir)
        outfile.write("\n".join(tree_structure))
        outfile.write("\n```\n\n---\n\n")

        # 2. Isi Kode
        print("Mulai menggabungkan file...")
        for root, dirs, files in os.walk(root_dir):
            # Abaikan folder hidden
            dirs[:] = [d for d in dirs if not d.startswith('.')]
            files.sort()
            
            for filename in files:
                if filename.endswith(".php") and filename != output_filename:
                    full_path = os.path.join(root, filename)
                    relative_path = os.path.relpath(full_path, root_dir)
                    
                    print(f"Menambahkan: {relative_path}")
                    outfile.write(f"## File: `{relative_path}`\n\n")
                    outfile.write("```php\n")
                    
                    try:
                        with open(full_path, "r", encoding="utf-8") as infile:
                            outfile.write(infile.read())
                    except Exception as e:
                        outfile.write(f"// Error membaca file: {e}")
                    
                    outfile.write("\n```\n\n---\n\n")

    print(f"\nSelesai! Periksa file '{output_filename}'")

if __name__ == "__main__":
    merge_php_to_markdown()