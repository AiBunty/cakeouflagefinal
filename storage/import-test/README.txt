Generated files:
- cakeitaway-hierarchy-master-200.csv (200 rows)
- cakeitaway-hierarchy-master-150.csv (150 rows)

Expected soft-delete outcome:
- Import 200 commit, then 150 commit -> deleted_count should be 50

Removed SKUs in 150 file:
CKF-MT-0151, CKF-MT-0152, CKF-MT-0153, CKF-MT-0154, CKF-MT-0155, CKF-MT-0156, CKF-MT-0157, CKF-MT-0158, CKF-MT-0159, CKF-MT-0160, CKF-MT-0161, CKF-MT-0162, CKF-MT-0163, CKF-MT-0164, CKF-MT-0165, CKF-MT-0166, CKF-MT-0167, CKF-MT-0168, CKF-MT-0169, CKF-MT-0170, CKF-MT-0171, CKF-MT-0172, CKF-MT-0173, CKF-MT-0174, CKF-MT-0175, CKF-MT-0176, CKF-MT-0177, CKF-MT-0178, CKF-MT-0179, CKF-MT-0180, CKF-MT-0181, CKF-MT-0182, CKF-MT-0183, CKF-MT-0184, CKF-MT-0185, CKF-MT-0186, CKF-MT-0187, CKF-MT-0188, CKF-MT-0189, CKF-MT-0190, CKF-MT-0191, CKF-MT-0192, CKF-MT-0193, CKF-MT-0194, CKF-MT-0195, CKF-MT-0196, CKF-MT-0197, CKF-MT-0198, CKF-MT-0199, CKF-MT-0200

Columns:
product_name, category_slug, category_name, subcategory_slug, subcategory_name, description, price, discount_price, sku, stock, tags, variant_info, image_url, image_url_1, image_url_2

Note:
- image_url_1 and image_url_2 are primary and secondary image slots.
- image_url kept for backward compatibility and intentionally empty in this fixture.