#include<iostream>
#include<cstdio>
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

void init(){
  stmt->execute("USE " + DATABASE);
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

void setBuildingDist(int u, int v, int t, P exits){
  char str[1080];

  
  sprintf(str, "INSERT INTO checkpos_data_89 (%s, %s, %s, %s, %s) VALUES(%d, %d, %d, %d, %d)",
	 "cd_bd_pid1", "cd_bd_pid2", "cd_node1", "cd_node2", "cd_time",
	  u, v, exits.first, exits.second, t
	 );
  
  cout << str << endl;
  stmt->executeQuery(str);
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
  
  int distB[B+1][B+1];
  P usedExit[B+1][B+1];
  for(int i = 1;i <= B;i++){
    for(int j = 1;j <= B;j++){
      distB[i][j] = INF;
    }
  }

  wf();
  
  for(int i = 1;i <= N;i++){
    vector<int> buildI = buildingList(i);
    for(int j = 1;j <= N;j++){
      if(i == j)continue;
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

  
  for(int i = 1;i <= B;i++){
    for(int j = 1;j <= B;j++){
      if(i == j)continue;
      cout << i << " " << j << endl;
      setBuildingDist(i, j, distB[i][j], usedExit[i][j]);
    }
  }
  
  return 0;
}
