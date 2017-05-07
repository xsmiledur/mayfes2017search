g++ buildingDist.cpp -o buildingDist -std=gnu++0x -I ../mySQLlib/include -L ../mySQLlib/lib -lmysqlcppconn -O2 -g
g++ uploadData.cpp -o uploadData -std=gnu++0x -I ../mySQLlib/include -L ../mySQLlib/lib -lmysqlcppconn -O2 -g
g++ reconstructRoute.cpp -o reconstructRoute -std=gnu++0x -I ../mySQLlib/include -L ../mySQLlib/lib -lmysqlcppconn -O2 -g
