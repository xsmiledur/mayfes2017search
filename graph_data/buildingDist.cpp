#include<iostream>
#include<cstdio>
#include<cstring>
#include<sstream>
#include<memory>
#include<mysql_driver.h>
#include<mysql_connection.h>
#include<mysql_error.h>
#include<cppconn/statement.h>
#include<cppconn/resultset.h>

#include<algorithm>
#include<vector>
#include<map>

using namespace std;
typedef pair<int, int> P;

#include "../SQLlib/mysql_config.hpp"
#include "../graph_data/config.hpp"
#define INF (1 << 28)

sql::mysql::MySQL_Driver *driver = sql::mysql::get_mysql_driver_instance();
unique_ptr<sql::Connection> con(driver->connect(HOST, USER, PASSWORD));
unique_ptr<sql::Statement> stmt(con->createStatement());
/*********************
g++ -std=gnu++0x -I/home/kenogura/Documents/mySQLlib/include/ buildingDist.cpp  -L /home/kenogura/Documents/mySQLlib/lib -lmysqlcppconn -o buildingDist


stmt -> execute ("SQL statement");
f.g stmt->execute("USE " + DATABASE);

unique_ptr<sql::ResultSet> res(stmt->executeQuery("SELECT * FROM conference"));

size_t row = 0;
while (res->next()) {
            cout << row << "\t";
            cout << "cid = " << res->getInt(1);
            cout << ", name = '" << res->getString("name") << "' " << endl;
            row++;
}
*********************/

int shortest[1080][1080];
int distB[1080][1080];
  P usedExit[1080][1080];

void init(){
  stmt->execute("USE " + DATABASE);
  /*
  stmt->execute("DROP TABLE IF EXISTS checkpos_data_89");
  stmt->execute("CREATE TABLE checkpos_data_89(cd_pid int(11) unsigned NOT NULL AUTO_INCREMENT, cd_bd_pid1 int(11) DEFAULT NULL, cd_bd_pid2 int(11) DEFAULT NULL, cd_node1 int(11) DEFAULT NULL, cd_node2 int(11) DEFAULT NULL, cd_time int(11) DEFAULT NULL, cd_active_flg tinyint(1) NOT NULL DEFAULT 1, primary key(cd_pid))");
  */
  stmt->execute("DROP TABLE IF EXISTS graph_data_89.dist_data_89");
  stmt->execute("CREATE TABLE graph_data_89.dist_data_89(dist_pid int(11) unsigned NOT NULL AUTO_INCREMENT, from_id int(3) DEFAULT NULL, to_id int(3) DEFAULT NULL, distance int(11) DEFAULT NULL, primary key(dist_pid))");
}

int getNodeNumber(){
  return NODE_NUMBER;
}

int getBuildingNumber(){
  return BUILDING_NUMBER;
}

int readInt(string str){
  istringstream os(str);
  int res;
  os >> res;
  return res;
}

int dist(int u, int v){
  char str[1080];
  sprintf(str, "SELECT * FROM graph_data_89.adjacent_data_89 WHERE from_id = %d AND to_id = %d", u, v);
  unique_ptr<sql::ResultSet> res(stmt->executeQuery(str));
  while(res->next()){
    return readInt(res->getString("edge_cost"));
  }
  return INF;
}



int realDist(int u, int v){
  return shortest[u][v];
}


void setDistance(int n){
  char str[108000];
  char tmp[108000];  
  sprintf(str, "INSERT INTO graph_data_89.dist_data_89 (%s, %s, %s) VALUES(%d, %d, %d)",
	  "from_id", "to_id", "distance"
	  ,1, 1, realDist(1, 1));
  for(int i = 1;i <= n;i++){
    for(int j = 1;j <= n;j++){
      if(i == 1 && j == 1)continue;
      sprintf(tmp, "%s", str);
      sprintf(str, "%s ,(%d, %d, %d)", tmp, i, j, realDist(i, j));
    }
  }
  stmt->execute(str);  
}



vector<int> buildingList(int id){
  vector<int> resV;
  char str[1080];
  sprintf(str, "SELECT * FROM graph_data_89.exit_data_89 WHERE node_id = %d", id);
  unique_ptr<sql::ResultSet> res(stmt->executeQuery(str));
  while(res->next()){
    resV.push_back(readInt(res->getString("building_id")));
  }
  return resV;
}

void setBuildingDist(int n){
  char str[1080000];
  char tmp[1080000];
  int B = getBuildingNumber();
  sprintf(str, "INSERT INTO checkpos_data_89 (%s, %s, %s, %s, %s) VALUES(%d, %d, %d, %d, %d)",
	 "cd_bd_pid1", "cd_bd_pid2", "cd_node1", "cd_node2", "cd_time",
	  1, 1, usedExit[1][1].first, usedExit[1][1].second, 0
	 );
  stmt->execute(str);

  
  for(int i = 1;i <= B;i++){
    for(int j = 1;j <= B;j++){
      if(i == 1 && j == 1)continue;
      if(distB[i][j] > 1080000)cerr << "INF dist!!" << " " << i << " " << j <<endl;
      sprintf(tmp, "%s", str);
      sprintf(str, "%s ,(%d, %d, %d, %d, %d)", tmp
	      ,i, j, usedExit[i][j].first, usedExit[i][j].second, distB[i][j]);
    }
  }  
  stmt->execute(str);
}


void wf(){
  int N = getNodeNumber();
  for(int i = 1;i <= N;i++){
    for(int j = 1;j <= N;j++){
      if(i == j)shortest[i][j] = 0;
      else shortest[i][j] = dist(i, j);
    }
  }

  for(int i = 1;i <= N;i++)
    for(int j = 1;j <= N;j++)
      for(int k = 1;k <= N;k++)
	shortest[j][k] = min(shortest[j][k], shortest[j][i] + shortest[i][k]);
}

int main(){
  init();
  int N = getNodeNumber();
  int B = getBuildingNumber();
  
  for(int i = 1;i <= B;i++){
    for(int j = 1;j <= B;j++){
      distB[i][j] = INF;
    }
  }

  wf();
  cout << "STEP1 : All to All distance" << endl;
  setDistance(N);
  cout << "Done" << endl;

  for(int i = 1;i <= N;i++){
    vector<int> buildI = buildingList(i);
    for(int j = 1;j <= N;j++){
      vector<int> buildJ = buildingList(j);
      for(int k = 0;k < buildI.size();k++){
	for(int l = 0;l < buildJ.size();l++){
	  int kid = buildI[k];
	  int jid = buildJ[l];
	  if(distB[kid][jid] > realDist(i, j)){
	    distB[kid][jid] = realDist(i, j);
	    usedExit[kid][jid] = P(i, j);
	  }
	}
      }
    }
  }

  cout << "STEP2 : Building to Building distance" << endl;
  setBuildingDist(N);
  cout << "Done" << endl;
  return 0;
}
