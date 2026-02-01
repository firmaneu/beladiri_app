import os

def build_tree(path, prefix=""):
    """Fungsi rekursif dengan pengelompokan: Folder dulu, baru File di akhir."""
    tree_lines = []
    
    try:
        # Ambil semua item dan sortir secara abjad
        items = sorted([item for item in os.listdir(path) if not item.startswith('.')])
    except PermissionError:
        return []

    # Pisahkan antara folder dan file .php
    folders = [i for i in items if os.path.isdir(os.path.join(path, i))]
    files = [i for i in items if not os.path.isdir(os.path.join(path, i)) and i.endswith('.php')]
    
    # Gabungkan kembali: Folder di atas, File di bawah
    reordered_items = folders + files

    for i, item in enumerate(reordered_items):
        is_last = (i == len(reordered_items) - 1)
        connector = "└── " if is_last else "├── "
        
        full_path = os.path.join(path, item)
        
        if os.path.isdir(full_path):
            tree_lines.append(f"{prefix}{connector}{item}/")
            # Teruskan rekursi untuk isi folder
            new_prefix = prefix + ("    " if is_last else "│   ")
            tree_lines.extend(build_tree(full_path, new_prefix))
        else:
            tree_lines.append(f"{prefix}{connector}{item}")
            
    return tree_lines

def merge_php_to_markdown(output_filename="SIM_PD_.md"):
    root_dir = os.getcwd()
    base_name = os.path.basename(root_dir) or "beladiri_app"
    
    with open(output_filename, "w", encoding="utf-8") as outfile: 
        # 1. Header & Tree
        outfile.write(f"# Dokumentasi Proyek: {base_name}\n\n")
        outfile.write("## Struktur Folder\n")
        outfile.write("```text\n")
        outfile.write(f"{base_name}/\n")
        
        tree_structure = build_tree(root_dir)
        outfile.write("\n".join(tree_structure))
        outfile.write("\n```\n\n---\n\n")

        # 2. Isi Kode (Sesuai urutan penelusuran folder)
        print("Sedang memproses isi file...")
        for root, dirs, filenames in os.walk(root_dir):
            dirs[:] = [d for d in dirs if not d.startswith('.')]
            filenames.sort()
            
            for filename in filenames:
                if filename.endswith(".php") and filename != output_filename:
                    full_path = os.path.join(root, filename)
                    relative_path = os.path.relpath(full_path, root_dir)
                    
                    outfile.write(f"## File: `{relative_path}`\n\n")
                    outfile.write("```php\n")
                    
                    try:
                        with open(full_path, "r", encoding="utf-8") as infile:
                            outfile.write(infile.read())
                    except Exception as e:
                        outfile.write(f"// Error membaca file: {e}")
                    
                    outfile.write("\n```\n\n---\n\n")

    print(f"\nBerhasil! Struktur pohon sekarang menampilkan folder di atas dan file root di bawah.")
    print(f"File output: {output_filename}")

if __name__ == "__main__":
    merge_php_to_markdown()