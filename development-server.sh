# rm -Rf _cache/*.xml
# rm -Rf _twigcache/*


# # rm -f _cache/read/c67571321fa966b6204720dfeae43b76.html
# rm -f _cache/read/*.html

rm -f _cache/b180345327557f4560fffe9b01b7aae4.xml
rm -f debug.log

php -S 0.0.0.0:3000 -t . router.php
